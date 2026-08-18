<?php

namespace App\Services;

use App\Models\StudentModel;

class StudentVerificationService
{
    /**
     * Upper bound on candidates in one storeBatch() call. The New Entry
     * form builds the list one row per click, so the largest batch ever
     * recorded is 113 -- this is a ceiling on abuse, not on real use.
     *
     * Public so the importer, its error text and the import screen's copy all
     * read the same number instead of hardcoding it three times.
     */
    public const MAX_BATCH_SIZE = 200;

    /**
     * @throws \InvalidArgumentException naming the candidate, the field and the
     *                                   limit, so the clerk can find the row
     */
    private function assertWithinLimits(array $data): void
    {
        foreach (self::COLUMN_LIMITS as $column => [$limit, $label]) {
            $value = (string) ($data[$column] ?? '');

            if ($value !== '' && mb_strlen($value) > $limit) {
                $who = (string) ($data['student_name'] ?? '');
                $who = $who === '' ? '' : ' for "' . mb_substr($who, 0, 40) . '"';

                throw new \InvalidArgumentException(
                    $label . $who . ' is too long (' . mb_strlen($value)
                    . ' characters; the limit is ' . $limit . ').'
                );
            }
        }
    }

    /**
     * How many times to step past an array_space that is already in use before
     * giving up and letting the write proceed. Bounded rather than a loop: a
     * collision needs one or two steps in practice, and an unbounded search
     * inside an open transaction is a worse failure than a rare merge.
     */
    private const MAX_ARRAY_SPACE_ATTEMPTS = 25;

    /**
     * student_details column widths, with the label each one wears on screen.
     *
     * Verified against information_schema. If a column is ever widened or
     * narrowed, this has to move with it -- the same contract
     * StudentImportService::DEST_LIMITS carries for the import path.
     */
    private const COLUMN_LIMITS = [
        'to_name'                               => [200, 'Addressee name'],
        'clg_add'                               => [1500, 'University address'],
        'admission_taken_year'                  => [25,  'Academic year'],
        'student_name'                          => [100, 'Candidate name'],
        'student_nee_name'                      => [200, 'Candidate former name'],
        'eligibility_case_no'                   => [60,  'Eligibility case number'],
        'admission_taken_in'                    => [60,  'Course'],
        'verification_of_marksheet_done_by_you' => [60,  'Verification done by'],
        'in_favour_of'                          => [200, 'Payment favouring title'],
        'email'                                 => [190, 'Email address'],
        'array_space'                           => [30,  'Batch reference'],
    ];

    protected StudentModel $studentModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->studentModel        = new StudentModel();
        $this->activityLogService  = new ActivityLogService();
    }

    public function getNextCommonNo(): int
    {
        return $this->studentModel->getMaxId() + 1;
    }

    /**
     * @param string $source How the batch was entered -- 'manual' from the New
     *                       Entry form, 'import' from the Excel importer. Used
     *                       only to label the audit-trail entry, never stored
     *                       on the rows, so both paths write identical records.
     */
    public function storeCandidateBatch(array $payload, string $username, string $source = 'manual'): array
    {
        if (empty($payload['students']) || !is_array($payload['students'])) {
            throw new \InvalidArgumentException('No valid candidate entries provided in batch payload.');
        }

        // This is the app's only JSON-body write endpoint, and it previously
        // validated exactly one thing: that 'students' was a non-empty array.
        // Size was unbounded, so a single request could ask for an arbitrary
        // number of INSERTs. The New Entry form adds one row per click, so no
        // legitimate batch comes close to this ceiling; it exists purely to
        // stop one request from becoming an unbounded write.
        if (count($payload['students']) > self::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException(
                'A single batch cannot contain more than ' . self::MAX_BATCH_SIZE . ' candidates.'
            );
        }

        // common_no arrives in the raw JSON body and was the ONLY value here
        // that reached the database without passing through sanitize_xss():
        // it is concatenated straight into array_space, the batch grouping
        // key that later appears in URLs (pdf/dispatch/<array_space>) and in
        // every batch screen. It is always an integer produced by
        // getNextCommonNo() and echoed back by the form, so coerce it rather
        // than trust the wire. A non-numeric, negative or absent value falls
        // back to the next real batch number -- exactly the previous default.
        $requestedCommonNo = (int) ($payload['common_no'] ?? 0);

        // One transaction for the whole batch, matching the sibling write path
        // (ConfirmationModel::storeConfirmationBatch). Previously each
        // candidate was an independent INSERT with its return value ignored:
        // a failure part-way through (deadlock, dropped connection, lock
        // timeout, an over-long value) left every earlier candidate committed
        // and abandoned the rest. The controller then reported "Save failed",
        // so the operator re-submitted and produced a SECOND partial batch --
        // and the dispatch letter for that array_space printed whichever
        // subset happened to land. All-or-nothing removes that entirely.
        $db = $this->studentModel->db;
        $db->transStart();

        // Derived INSIDE the transaction, and guarded against collision.
        //
        // common_no is rendered into the New Entry page at load and posted back
        // unchanged, so two batches saved from two tabs -- or one long-running
        // import -- can carry the same number. array_space would then be
        // identical for both, and because it is the batch grouping key with no
        // uniqueness constraint, the two batches SILENTLY MERGE: every batch
        // screen shows them as one, and the dispatch letter reads its header
        // fields from whichever row comes back first, so it can print the wrong
        // university entirely.
        //
        // Taking the number here rather than before transStart() closes most of
        // the window; the existence check closes the rest by stepping past a
        // number already in use instead of joining it.
        $commonNo = $requestedCommonNo > 0 ? $requestedCommonNo : $this->getNextCommonNo();

        $arraySpace = $username . '_' . $commonNo;

        for ($attempt = 0; $attempt < self::MAX_ARRAY_SPACE_ATTEMPTS; $attempt++) {
            if (! $this->studentModel->arraySpaceExists($arraySpace)) {
                break;
            }

            $commonNo++;
            $arraySpace = $username . '_' . $commonNo;
        }

        $insertedCount = 0;
        foreach ($payload['students'] as $stud) {
            $data = [
                'to_name'                               => sanitize_xss($payload['to_name'] ?? ''),
                'array_space'                           => $arraySpace,
                'clg_add'                               => sanitize_xss($payload['clg_add'] ?? ''),
                'admission_taken_year'                  => sanitize_xss($payload['admission_taken_year'] ?? ''),
                'student_name'                          => sanitize_xss($stud['student_name'] ?? ''),
                'student_nee_name'                      => sanitize_xss($stud['student_nee_name'] ?? '-'),
                'eligibility_case_no'                   => sanitize_xss($stud['eligibility_case_no'] ?? ''),
                'admission_taken_in'                    => sanitize_xss($payload['admission_taken_in'] ?? ''),
                'verification_of_marksheet_done_by_you' => sanitize_xss($stud['verification_by_you'] ?? ''),
                'in_favour_of'                          => sanitize_xss($payload['in_favour_of'] ?? ''),
                // Optional -- the form validates the shape client-side, but
                // never trust that alone; an empty/invalid value is stored as
                // NULL rather than junk, since BulkEmailService treats "no
                // valid address" as a skip, not an error.
                'email'                                 => $this->normalizedEmail($stud['email'] ?? ''),
                'en_time'                               => time()
            ];

            // Checked before the write, not after MySQL refuses it. Every
            // field here is free text a clerk typed, and the whole batch runs
            // inside one transaction -- so one over-long name used to roll back
            // every other candidate keyed in alongside it, with an error that
            // named none of them.
            $this->assertWithinLimits($data);

            $this->studentModel->insert($data);
            $insertedCount++;
        }

        $db->transComplete();

        // transStatus() is false if ANY statement in the block failed, in
        // which case transComplete() has already rolled the whole batch back.
        // Throwing here keeps the existing contract: Students::storeBatch
        // catches \Exception and returns {status:'error'}, which is exactly
        // what the operator should see -- the difference is that now nothing
        // was written, so a re-submit produces one clean batch rather than a
        // duplicated partial one.
        if ($db->transStatus() === false) {
            throw new \RuntimeException('The candidate batch could not be saved. No records were written -- please try again.');
        }

        // Recorded only after the transaction committed, so the trail never
        // claims a batch that was rolled back.
        //
        // This was the one bulk operation in the app with no audit entry:
        // bulk delete and bulk export are both logged -- and the export
        // logging comment reasons that "who took a copy of the candidate list,
        // and when, is precisely the question an audit trail exists to answer".
        // Who CREATED a hundred candidate records, and from where, is at least
        // as much of a question. It matters more now the importer can add 200
        // rows from a file that is never retained.
        $this->activityLogService->record(
            'student.batch_create',
            'student_batch',
            null,
            'Created batch ' . $arraySpace . ' with ' . $insertedCount . ' candidate(s) via ' . $source
        );

        return [
            'count'       => $insertedCount,
            'array_space' => $arraySpace
        ];
    }

    /** @return string|null a validated address, or null if blank/malformed */
    private function normalizedEmail($raw): ?string
    {
        $email = trim(sanitize_xss((string) $raw));

        return ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) ? $email : null;
    }

    public function getStudentsByArraySpace(string $arraySpace): array
    {
        return $this->studentModel->getStudentsByArraySpace($arraySpace);
    }

    public function getStudentById(int $id): ?array
    {
        $res = $this->studentModel->find($id);

        return $res ?: null;
    }

    public function searchStudentsForReminder(?string $year, ?string $stream, ?string $university): array
    {
        return $this->studentModel->getStudentsForReminder($year, $stream, $university);
    }

    /** The pager the last paginated query populated -- read after calling searchStudentsForReminder(). */
    public function getReminderPager(): ?\CodeIgniter\Pager\PagerInterface
    {
        return $this->studentModel->pager;
    }

    /** Same filters as searchStudentsForReminder() above, unpaginated -- feeds the Excel export. */
    public function searchStudentsForReminderAll(?string $year, ?string $stream, ?string $university): array
    {
        return $this->studentModel->getStudentsForReminderAll($year, $stream, $university);
    }

    public function getTotalStudentsCount(): int
    {
        return $this->studentModel->getTotalStudentsCount();
    }

    /** Feeds the batch history/browse page -- mirrors esection_basic's view.php. */
    public function getBatchSummaries(): array
    {
        return $this->studentModel->getBatchSummaries();
    }

    /**
     * The 4 fields esection_basic's own update_new_form.php allowed editing,
     * plus email (added 2026-08-05 for bulk email; university/year/course
     * were shown but disabled there too, since changing them would misfile
     * the record into a different batch -- email carries no such risk).
     *
     * @throws \InvalidArgumentException on invalid input or missing record
     */
    public function updateStudent(int $id, array $postData): void
    {
        $student = $this->studentModel->find($id);
        if (! $student) {
            throw new \InvalidArgumentException('Student record not found.');
        }

        $studentName = trim(sanitize_xss($postData['student_name'] ?? ''));
        if ($studentName === '') {
            throw new \InvalidArgumentException('Student name is required.');
        }

        $data = [
            'student_name'                           => $studentName,
            'student_nee_name'                        => sanitize_xss($postData['student_nee_name'] ?? ''),
            'eligibility_case_no'                     => sanitize_xss($postData['eligibility_case_no'] ?? ''),
            'verification_of_marksheet_done_by_you'   => sanitize_xss($postData['verification_of_marksheet_done_by_you'] ?? ''),
            'email'                                   => $this->normalizedEmail($postData['email'] ?? ''),
        ];

        // Edit gets the same check as create. Every field on this form is free
        // text, and eligibility_case_no in particular is only varchar(60)
        // against an input with no maxlength of its own.
        $this->assertWithinLimits($data);

        $this->studentModel->update($id, $data);

        $this->activityLogService->record('student.update', 'student', $id, 'Updated candidate ' . $studentName);
    }

    /**
     * Hard delete -- mirrors esection_basic's config/stud-delete.php `?q=` branch.
     *
     * @throws \InvalidArgumentException when the record doesn't exist
     */
    public function deleteStudent(int $id): void
    {
        if (! feature_enabled('feature_delete_enabled')) {
            throw new \InvalidArgumentException('Deleting records is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        $student = $this->studentModel->find($id);
        if (! $student) {
            throw new \InvalidArgumentException('Student record not found.');
        }

        $this->studentModel->delete($id);

        $this->activityLogService->record('student.delete', 'student', $id, 'Deleted candidate ' . $student['student_name']);
    }
}
