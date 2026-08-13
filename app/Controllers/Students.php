<?php

namespace App\Controllers;

use App\Services\DocumentNumberingService;
use App\Services\ExcelExportService;
use App\Services\StudentImportService;
use App\Services\StudentVerificationService;
use App\Services\UniversityService;

class Students extends BaseController
{
    protected StudentVerificationService $studentService;
    protected UniversityService $universityService;
    protected DocumentNumberingService $documentNumberingService;
    protected StudentImportService $studentImportService;

    public function __construct()
    {
        $this->studentService           = new StudentVerificationService();
        $this->universityService        = new UniversityService();
        $this->documentNumberingService = new DocumentNumberingService();
        $this->studentImportService     = new StudentImportService();
    }

    public function newForm()
    {
        $data = [
            'title'          => 'New Student Verification Form',
            'common_no'      => $this->studentService->getNextCommonNo(),
            // 'colleges' removed: this materialised all 469 college_details
            // rows (two varchar(1500) columns) on every page open, and
            // students/new_form.php never referenced $colleges. The university
            // picker here is a Select2 fed by GET /api/colleges. getAllColleges()
            // itself stays -- Universities::index() still uses it.
            'suggestedCaseNo' => $this->documentNumberingService->previewNext(),
        ];

        return view('students/new_form', $data);
    }

    /**
     * A fresh suggested case number, in the configured Settings > Document
     * Numbering format -- called after each candidate is added, so the next
     * row starts pre-filled instead of every candidate getting today's first
     * suggestion. Always editable; this only changes the starting value.
     */
    public function generateCaseNo()
    {
        return $this->response->setJSON(['case_no' => $this->documentNumberingService->previewNext()]);
    }

    public function getCollegeInfo($id)
    {
        $college = $this->universityService->getCollegeById((int)$id);
        if ($college) {
            return $this->response->setJSON([
                'status'       => 'success',
                'address'      => $college['Address'],
                'in_favour_of' => $college['in_favour_of'],
                'fees'         => $college['fees'],
                'head_name'    => $college['head_name']
            ]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'University not found']);
    }

    public function storeBatch()
    {
        $json = $this->request->getJSON(true);

        // No silent 'esection1' fallback: array_space is built from this
        // value, so an absent session would have filed the batch under
        // another real operator's name and mis-attributed the audit trail.
        // AuthFilter guarantees a session here, so this can only be a bug.
        $username = (string) (session()->get('username') ?? '');

        if ($username === '') {
            log_message('error', '[Students::storeBatch] no username in session for an authenticated request.');

            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Your session has expired. Please log in again and re-submit the batch.',
            ]);
        }

        try {
            $res = $this->studentService->storeCandidateBatch($json ?: [], $username);

            return $this->response->setJSON([
                'status'       => 'success',
                'message'      => "{$res['count']} student verification cases saved successfully.",
                'array_space'  => $res['array_space'],
                'redirect_url' => base_url('pdf/dispatch/' . urlencode($res['array_space']))
            ]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            // MUST sit before the \RuntimeException arm below. DatabaseException
            // extends CodeIgniter\Exceptions\RuntimeException, which extends
            // \RuntimeException -- so that arm caught it first and returned
            // getMessage() verbatim, which with DBDebug on carries the driver's
            // own text and can quote the failing SQL. The \Throwable arm further
            // down was written to stop exactly that and could never be reached.
            log_message('error', '[Students::storeBatch] {message}', ['message' => (string) $e]);

            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'The batch could not be saved. The issue has been logged.',
            ]);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            // Our own validation/atomicity failures. These messages are
            // written for the operator, so they are safe to show verbatim.
            return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // Anything else -- notably DatabaseException, which with
            // DBDebug=true carries the driver's own text (and can quote the
            // failing SQL) -- must not reach the browser. Same split
            // Confirmations::store() already uses.
            log_message('error', '[Students::storeBatch] {message}', ['message' => (string) $e]);

            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'The verification batch could not be saved. Please try again.',
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Excel import
    // -----------------------------------------------------------------

    public function importForm()
    {
        return view('students/import_form', [
            'title'     => 'Import Candidates from Excel',
            'maxSizeKb' => StudentImportService::MAX_SIZE_KB,
            'maxBatch'  => StudentVerificationService::MAX_BATCH_SIZE,
        ]);
    }

    /** The blank workbook, with the admission export's own 24 headings. */
    public function importTemplate()
    {
        try {
            $path = $this->studentImportService->buildTemplate();
        } catch (\Throwable $e) {
            log_message('error', '[Students::importTemplate] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('students/import'))
                ->with('error', 'The template could not be generated. The issue has been logged.');
        }

        try {
            return $this->response
                ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->setHeader('Content-Disposition', 'attachment; filename="esection_import_template.xlsx"')
                ->setBody((string) file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    /**
     * Parse and validate the upload. Writes NOTHING -- the operator sees the
     * preview and only then confirms.
     */
    public function importPreview()
    {
        set_time_limit(0);

        try {
            $preview = $this->studentImportService->previewUpload(
                $this->request->getFile('import_file'),
                (array) ($this->request->getPost('boards') ?? []),
                (array) ($this->request->getPost('courses') ?? [])
            );

            return $this->response->setJSON(['status' => 'success', 'data' => $preview]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            // MUST sit before the \RuntimeException arm below. DatabaseException
            // extends CodeIgniter\Exceptions\RuntimeException, which extends
            // \RuntimeException -- so that arm caught it first and returned
            // getMessage() verbatim, which with DBDebug on carries the driver's
            // own text and can quote the failing SQL. The \Throwable arm further
            // down was written to stop exactly that and could never be reached.
            log_message('error', '[Students::importPreview] {message}', ['message' => (string) $e]);

            return $this->response->setStatusCode(500)
                ->setJSON(['status' => 'error', 'message' => 'The file could not be read. The issue has been logged.']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            // Written for the operator -- safe to show verbatim.
            return $this->response->setStatusCode(422)
                ->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[Students::importPreview] {message}', ['message' => (string) $e]);

            return $this->response->setStatusCode(500)
                ->setJSON(['status' => 'error', 'message' => 'The file could not be read. The issue has been logged.']);
        }
    }

    /**
     * Write the import. The file is re-sent and re-parsed rather than trusting
     * rows held in the browser, so the server remains the only source of what
     * gets written.
     */
    public function importCommit()
    {
        set_time_limit(0);

        $username = (string) (session()->get('username') ?? '');

        if ($username === '') {
            log_message('error', '[Students::importCommit] no username in session for an authenticated request.');

            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => 'Your session has expired. Please log in again and re-run the import.',
            ]);
        }

        try {
            $result = $this->studentImportService->commitImport(
                $this->request->getFile('import_file'),
                (array) ($this->request->getPost('boards') ?? []),
                (array) ($this->request->getPost('courses') ?? []),
                $username
            );

            return $this->response->setJSON(['status' => 'success', 'data' => $result]);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            // MUST sit before the \RuntimeException arm below. DatabaseException
            // extends CodeIgniter\Exceptions\RuntimeException, which extends
            // \RuntimeException -- so that arm caught it first and returned
            // getMessage() verbatim, which with DBDebug on carries the driver's
            // own text and can quote the failing SQL. The \Throwable arm further
            // down was written to stop exactly that and could never be reached.
            log_message('error', '[Students::importCommit] {message}', ['message' => (string) $e]);

            return $this->response->setStatusCode(500)
                ->setJSON(['status' => 'error', 'message' => 'The import could not be completed. The issue has been logged.']);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->response->setStatusCode(422)
                ->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', '[Students::importCommit] {message}', ['message' => (string) $e]);

            return $this->response->setStatusCode(500)
                ->setJSON(['status' => 'error', 'message' => 'The import could not be completed. The issue has been logged.']);
        }
    }

    /** Browse previously submitted batches -- mirrors esection_basic's view.php. */
    public function history()
    {
        $data = [
            'title'   => 'Verification Batch History',
            'batches' => $this->studentService->getBatchSummaries(),
        ];

        return view('students/history', $data);
    }

    /** No server-side filters on this page -- every batch, always. */
    public function historyExport()
    {
        $batches = $this->studentService->getBatchSummaries();

        $columns = [
            ['header' => 'Batch'],
            ['header' => 'University Address', 'width' => 35],
            ['header' => 'Admission Taken In'],
            ['header' => 'Academic Year'],
            ['header' => 'Candidates', 'format' => '#,##0'],
            ['header' => 'Created', 'format' => 'dd/mm/yyyy hh:mm'],
        ];

        $rows = array_map(static function (array $b): array {
            $created = is_numeric($b['en_time'])
                ? (new \DateTimeImmutable())->setTimestamp((int) $b['en_time'])
                : $b['en_time'];

            return [
                $b['array_space'],
                $b['clg_add'],
                $b['admission_taken_in'],
                $b['admission_taken_year'],
                (int) $b['student_count'],
                $created,
            ];
        }, $batches);

        $summaryLine = 'Filters: none | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, 'students_history', $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('students/history'))->with('error', $e->getMessage());
        }
    }

    /** One batch's full student list, for the browse/edit/delete view. */
    public function batchDetail($arraySpace)
    {
        $data = [
            'title'      => 'Verification Batch Detail',
            'arraySpace' => $arraySpace,
            'students'   => $this->studentService->getStudentsByArraySpace($arraySpace),
        ];

        return view('students/batch_detail', $data);
    }

    public function getJson($id)
    {
        $student = $this->studentService->getStudentById((int) $id);
        if ($student) {
            return $this->response->setJSON(['status' => 'success', 'data' => $student]);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Student not found']);
    }

    /** Mirrors esection_basic's update_new_form.php -> config/stud_update_data.php (only 4 fields are editable there too). */
    public function update($id)
    {
        // Read before the write so the batch is known even if the update throws.
        $existing = $this->studentService->getStudentById((int) $id);

        try {
            $this->studentService->updateStudent((int) $id, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respondForBatch(false, $e->getMessage(), $existing['array_space'] ?? null);
        }

        return $this->respondForBatch(true, 'Candidate updated.', $existing['array_space'] ?? null);
    }

    /** Hard delete -- mirrors esection_basic's config/stud-delete.php `?q=` branch. */
    public function delete($id)
    {
        // The row is about to disappear, so its batch has to be read first --
        // afterwards there is nothing left to derive it from.
        $existing   = $this->studentService->getStudentById((int) $id);
        $arraySpace = $existing['array_space'] ?? null;

        try {
            $this->studentService->deleteStudent((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->respondForBatch(false, $e->getMessage(), $arraySpace);
        }

        return $this->respondForBatch(true, 'Candidate deleted.', $arraySpace);
    }

    /**
     * Reply for the two batch-detail POSTs.
     *
     * The fallback stays redirect()->back() (null): this page is reached from
     * the history list and CodeIgniter stores _ci_previous_url with its query
     * string, so a fixed URL would strand a no-JavaScript operator away from
     * the batch they were working in.
     *
     * `remaining` is what lets the client leave a batch it has just emptied.
     * batchDetail() has no emptiness guard, so a page for a deleted batch still
     * renders -- with PDF buttons that return a bare text string rather than a
     * document. A full reload used to make that invisible.
     */
    private function respondForBatch(bool $ok, string $message, ?string $arraySpace)
    {
        $extra = [];

        if ($this->request->isAJAX() && $arraySpace !== null) {
            $students = $this->studentService->getStudentsByArraySpace($arraySpace);

            $extra = [
                'html'      => view('students/_batch_rows', ['students' => $students]),
                'remaining' => count($students),
            ];
        }

        return $this->respondToPost($ok, $message, null, $extra);
    }
}
