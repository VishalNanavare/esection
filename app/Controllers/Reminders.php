<?php

namespace App\Controllers;

use App\Models\StudentModel;
use App\Services\ExcelExportService;
use App\Services\UniversityService;
use App\Services\StudentVerificationService;
use App\Services\PdfGenerationService;
use App\Services\LetterTemplateService;
use App\Services\UniversityReminderService;
use App\Services\StudentReminderService;

class Reminders extends BaseController
{
    protected StudentModel $studentModel;
    protected UniversityService $universityService;
    protected StudentVerificationService $studentService;
    protected PdfGenerationService $pdfService;
    protected LetterTemplateService $letterTemplateService;
    protected UniversityReminderService $universityReminderService;
    protected StudentReminderService $studentReminderService;

    public function __construct()
    {
        $this->studentModel      = new StudentModel();
        $this->universityService = new UniversityService();
        $this->studentService    = new StudentVerificationService();
        $this->pdfService        = new PdfGenerationService();
        $this->letterTemplateService = new LetterTemplateService();
        $this->universityReminderService = new UniversityReminderService();
        $this->studentReminderService     = new StudentReminderService();
    }

    public function university()
    {
        // GET, not POST: all 3 filters are optional (blank = unrestricted,
        // so the page always shows real data on open), and pager links are
        // always plain <a href> GET navigations -- a POST-submitted filter
        // would be silently lost the moment a pager link was clicked.
        // trim(), NOT sanitize_xss(): these are search terms, not stored
        // values. sanitize_xss() is htmlspecialchars(), so it rewrites "&"
        // to "&amp;" and an apostrophe to "&#039;" BEFORE the value is bound
        // into the query -- against columns that hold the original
        // characters. The filter then matches nothing: picking any of the
        // universities whose name contains "&" (187 student rows on
        // "U.P. Board of High School & Intermediate" alone) returned an
        // empty page. Both consumers are already safe without it -- the
        // Query Builder escapes its own binds, and the view re-emits these
        // through esc() / urlencode().
        $selectedYear   = trim((string) ($this->request->getGet('acd_year') ?? ''));
        $selectedStream = trim((string) ($this->request->getGet('stream') ?? ''));
        $selectedColg   = trim((string) ($this->request->getGet('clg_add') ?? ''));

        $students = $this->studentService->searchStudentsForReminder($selectedYear, $selectedStream, $selectedColg);

        $noteCounts = $this->universityReminderService->getNoteCountsForStudents(array_column($students, 'id'));
        foreach ($students as &$s) {
            $s['reminder_note_count'] = $noteCounts[(int) $s['id']] ?? 0;
        }
        unset($s);

        $data = [
            'title'           => 'University Marksheet Reminder Portal',
            // 'streams' and 'colleges' removed: reminders/university.php
            // references neither (nor does ajax_reminders_js or the layout).
            // Both pickers on this page are Select2 widgets fed by
            // GET /api/streams and GET /api/colleges, so loading all 469
            // universities server-side was pure discarded work on every open.
            'students'        => $students,
            'pager'           => $this->studentService->getReminderPager(),
            'selected_year'   => $selectedYear,
            'selected_stream' => $selectedStream,
            'selected_colg'   => $selectedColg,
        ];

        return view('reminders/university', $data);
    }

    /**
     * Same 3 filters as university() above, unpaginated -- exports every
     * matching row. Must repeat university()'s note-count merge: the
     * "Reminder Status" column is not a student_details column, it's
     * computed separately -- skipping this merge would silently export an
     * empty/wrong column.
     */
    public function universityExport()
    {
        // Same reasoning as university() above -- and the export MUST use the
        // identical treatment, or the file would not match the screen it was
        // exported from.
        $selectedYear   = trim((string) ($this->request->getGet('acd_year') ?? ''));
        $selectedStream = trim((string) ($this->request->getGet('stream') ?? ''));
        $selectedColg   = trim((string) ($this->request->getGet('clg_add') ?? ''));

        $students = $this->studentService->searchStudentsForReminderAll($selectedYear, $selectedStream, $selectedColg);

        $noteCounts = $this->universityReminderService->getNoteCountsForStudents(array_column($students, 'id'));
        foreach ($students as &$s) {
            $s['reminder_note_count'] = $noteCounts[(int) $s['id']] ?? 0;
        }
        unset($s);

        $columns = [
            ['header' => 'Candidate Name'],
            ['header' => 'Eligibility Case No.'],
            ['header' => 'Target University Address', 'width' => 35],
            ['header' => 'Academic Year'],
            ['header' => 'Reminder Status', 'format' => '#,##0'],
        ];

        $rows = array_map(static function (array $s): array {
            return [
                $s['student_name'],
                $s['eligibility_case_no'],
                $s['clg_add'],
                $s['admission_taken_year'],
                (int) $s['reminder_note_count'],
            ];
        }, $students);

        $filterParts = [];
        if ($selectedYear !== '')   { $filterParts[] = 'Year=' . $selectedYear; }
        if ($selectedStream !== '') { $filterParts[] = 'Stream=' . $selectedStream; }
        if ($selectedColg !== '')   { $filterParts[] = 'University=' . $selectedColg; }
        $summaryLine = 'Filters: ' . ($filterParts === [] ? 'none' : implode(', ', $filterParts))
            . ' | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        $filenameBase = 'reminders_university'
            . ($selectedYear !== '' ? '_year-' . preg_replace('/[^A-Za-z0-9-]/', '', $selectedYear) : '')
            . ($selectedStream !== '' ? '_stream-' . preg_replace('/[^A-Za-z0-9-]/', '', $selectedStream) : '');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, $filenameBase, $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('reminders/university'))->with('error', $e->getMessage());
        }
    }

    /**
     * Persists a reminder note per selected student (restoring
     * esection_basic's rem_db behavior, which CI4's rewrite had dropped
     * entirely) then reprints the PDF from the FULL batch -- every student
     * ever added to this (academic_year, university) batch, each showing
     * their complete note history, mirroring config/rem_conf.php pulling
     * every row for the batch's array_space rather than just this
     * submission's selection.
     */
    public function generateUniversityReminder()
    {
        $username = (string) (session()->get('username') ?? '');

        try {
            $result = $this->universityReminderService->addReminderNotes($this->request->getPost(), $username);
        } catch (\InvalidArgumentException $e) {
            // JSON for an XHR caller -- see Regularization::generateLetter().
            if ($this->request->isAJAX()) {
                return $this->respondToPost(false, $e->getMessage(), base_url('reminders/university'));
            }

            return redirect()->to(base_url('reminders/university'))->with('error', $e->getMessage());
        }

        if ($this->request->isAJAX()) {
            return $this->respondToPost(true, 'University reminder recorded.', base_url('reminders/university'), [
                'pdf_url' => base_url('reminders/university/pdf/' . (int) $result['batch_id']),
            ]);
        }

        // Non-AJAX response IS the document -- the form is target="_blank".
        return $this->renderBatchPdf((int) $result['batch_id']);
    }

    /** Browse reminder batches -- mirrors esection_basic's rem_view.php. */
    public function universityHistory()
    {
        $data = [
            'title'   => 'University Reminder History',
            'batches' => $this->universityReminderService->getBatchesSummary(),
        ];

        return view('reminders/university_history', $data);
    }

    /** No server-side filters on this page -- every batch, always. */
    public function universityHistoryExport()
    {
        $batches = $this->universityReminderService->getBatchesSummary();

        $columns = [
            ['header' => 'University', 'width' => 35],
            ['header' => 'Academic Year'],
            ['header' => 'Course'],
            ['header' => 'Candidates', 'format' => '#,##0'],
            ['header' => 'Started', 'format' => 'dd/mm/yyyy hh:mm'],
        ];

        $rows = array_map(static function (array $b): array {
            $started = !empty($b['created_at'])
                ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $b['created_at'])
                : null;

            return [
                $b['university_name'],
                $b['academic_year'],
                $b['admission_taken_in'] ?: '-',
                (int) $b['student_count'],
                $started ?: '-',
            ];
        }, $batches);

        $summaryLine = 'Filters: none | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, 'reminders_university_history', $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('reminders/university/history'))->with('error', $e->getMessage());
        }
    }

    /** One batch's full student + note detail, for the browse/update view. */
    public function universityBatchDetail($batchId)
    {
        try {
            $detail = $this->universityReminderService->getBatchDetail((int) $batchId);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('reminders/university/history'))->with('error', $e->getMessage());
        }

        return view('reminders/university_batch_detail', [
            'title'    => 'University Reminder Batch Detail',
            'batch'    => $detail['batch'],
            'students' => $detail['students'],
        ]);
    }

    /** Reprint a batch's letter -- mirrors esection_basic's config/rem_conf.php. */
    public function universityBatchPdf($batchId)
    {
        return $this->renderBatchPdf((int) $batchId);
    }

    private function renderBatchPdf(int $batchId)
    {
        // Same guard universityBatchDetail() already has. getBatchDetail()
        // throws InvalidArgumentException for an unknown id, and without
        // this the PDF route answered HTTP 500 (plus a CRITICAL stack trace
        // in the log) where its sibling redirects with a friendly message.
        // A stale link to a deleted batch is a normal outcome, not a server
        // error -- and this route is opened via window.open, so a 500 shows
        // the user a blank tab with no explanation.
        try {
            $detail = $this->universityReminderService->getBatchDetail($batchId);
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->to(base_url('reminders/university/history'))
                ->with('error', $e->getMessage());
        }

        $batch    = $detail['batch'];
        $students = $detail['students'];

        if ($students === []) {
            return redirect()->to(base_url('reminders/university/history'))->with('error', 'This batch has no students.');
        }

        $latestNoteText = $students[0]['notes'] !== [] ? end($students[0]['notes'])['note_text'] : '1st Reminder';

        $rendered = $this->letterTemplateService->render(
            'university_reminder',
            $this->letterTemplateService->getFields('university_reminder'),
            [
                'reminder_type' => $latestNoteText,
                'course'        => $batch['admission_taken_in'] ?? '',
                'academic_year' => $batch['academic_year'] ?? '',
            ]
        );

        $data = array_merge($rendered, [
            'students'      => $students,
            'first_row'     => ['clg_add' => $batch['university_name'], 'to_name' => $batch['head_name']],
            'reminder_type' => $latestNoteText,
            'date'          => date('d/m/Y'),
        ]);

        return $this->pdfService->renderPdfResponse('pdf/university_reminder_letter', $data, 'university_reminder_notice.pdf');
    }

    public function student()
    {
        $data = [
            'title' => 'Candidate Document Reminder Portal',
        ];
        return view('reminders/student', $data);
    }

    /**
     * Persists the letter (restoring esection_basic's student_rem behavior,
     * which CI4's rewrite had dropped entirely) and opens the PDF in the
     * same request/new tab, unchanged from today's user-facing behavior.
     */
    public function generateStudentReminder()
    {
        $username = (string) (session()->get('username') ?? '');

        try {
            $newId = $this->studentReminderService->create($this->request->getPost(), $username);
        } catch (\InvalidArgumentException $e) {
            // JSON for an XHR caller -- see Regularization::generateLetter().
            if ($this->request->isAJAX()) {
                return $this->respondToPost(false, $e->getMessage(), base_url('reminders/student'));
            }

            return redirect()->to(base_url('reminders/student'))->with('error', $e->getMessage());
        }

        if ($this->request->isAJAX()) {
            return $this->respondToPost(true, 'Candidate reminder created.', base_url('reminders/student'), [
                'pdf_url' => base_url('reminders/student/pdf/' . $newId),
            ]);
        }

        // Non-AJAX response IS the document -- the form is target="_blank".
        $record = $this->studentReminderService->getById($newId);
        $data   = $this->studentReminderService->buildLetterData($record);

        return $this->pdfService->renderPdfResponse('pdf/student_reminder_letter', $data, 'candidate_reminder_notice.pdf');
    }

    /** Browse previously generated letters -- mirrors esection_basic's student_rem_view.php. */
    public function studentHistory()
    {
        $data = [
            'title'   => 'Candidate Reminder History',
            'records' => $this->studentReminderService->getAll(),
        ];

        return view('reminders/student_history', $data);
    }

    /** No server-side filters on this page -- every reminder, always. */
    public function studentHistoryExport()
    {
        $records = $this->studentReminderService->getAll();

        $columns = [
            ['header' => 'Candidate'],
            ['header' => 'Case No.'],
            ['header' => 'Course'],
            ['header' => 'Missing Document(s)', 'width' => 35],
            ['header' => 'Created', 'format' => 'dd/mm/yyyy hh:mm'],
        ];

        $rows = array_map(static function (array $r): array {
            $created = !empty($r['created_at'])
                ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $r['created_at'])
                : null;

            return [
                $r['student_name'],
                $r['eligibility_case_no'],
                $r['course_name'],
                $r['missing_doc'],
                $created ?: '-',
            ];
        }, $records);

        $summaryLine = 'Filters: none | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, 'reminders_student_history', $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('reminders/student/history'))->with('error', $e->getMessage());
        }
    }

    /** Reprint a letter -- mirrors esection_basic's config/student_rem_pdf.php. */
    public function studentPdf($id)
    {
        $record = $this->studentReminderService->getById((int) $id);
        if (! $record) {
            return redirect()->to(base_url('reminders/student/history'))->with('error', 'Record not found.');
        }

        $data = $this->studentReminderService->buildLetterData($record);

        return $this->pdfService->renderPdfResponse('pdf/student_reminder_letter', $data, 'candidate_reminder_notice.pdf');
    }

    public function studentDelete($id)
    {
        try {
            $this->studentReminderService->delete((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->respondForStudentHistory(false, $e->getMessage());
        }

        return $this->respondForStudentHistory(true, 'Candidate reminder deleted.');
    }

    /** Reply for the candidate-reminder history POST -- see SettingsUsers::respond(). */
    private function respondForStudentHistory(bool $ok, string $message, int $failStatus = 422)
    {
        $extra = [];

        if ($this->request->isAJAX()) {
            $extra['html'] = view('reminders/_student_history_rows', [
                'records' => $this->studentReminderService->getAll(),
            ]);
        }

        return $this->respondToPost($ok, $message, base_url('reminders/student/history'), $extra, $failStatus);
    }
}
