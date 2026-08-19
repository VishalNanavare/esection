<?php

namespace App\Controllers;

use App\Services\DocumentNumberingService;
use App\Services\ExcelExportService;
use App\Services\CandidateSheetService;
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
        // Inside a try, because getJSON() throws HTTPException::forInvalidJSON
        // when the body will not decode (IncomingRequest.php:409). It sat
        // outside every catch arm below, so a truncated or malformed POST --
        // a dropped connection mid-upload is enough -- surfaced as an
        // uncaught 500 rather than as this endpoint's normal JSON error
        // envelope, which the New Entry screen knows how to display.
        try {
            $json = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            log_message('warning', '[Students::storeBatch] undecodable JSON body: {message}', [
                'message' => $e->getMessage(),
            ]);

            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'The batch could not be read. Please reload the page and try again.',
            ]);
        }

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

    /**
     * Reads an .xlsx of candidates and returns the rows for the New Form table.
     *
     * Writes nothing. The operator ticks what they want, fills in the
     * university, year and course as usual, and the ordinary Save button does
     * the actual work through storeBatch() -- so an imported batch and a typed
     * batch are the same batch, saved by the same validated, transactional
     * path.
     */
    public function readCandidateSheet()
    {
        set_time_limit(0);

        try {
            $result = (new CandidateSheetService())->read($this->request->getFile('candidate_sheet'));

            return $this->response->setJSON(['status' => 'success', 'data' => $result]);
        } catch (\InvalidArgumentException $e) {
            // Written for the operator -- "row 14 has no case number", not a
            // stack trace. 422 so the caller can tell a rejected sheet from a
            // broken server.
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[Students::readCandidateSheet] {type}: {msg}', [
                'type' => $e::class,
                'msg'  => $e->getMessage(),
            ]);

            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'That sheet could not be read. The issue has been logged.',
            ]);
        }
    }

    public function importForm()
    {
        // The screen itself, not just the upload. StudentImportService refuses
        // the file, but only once it has been chosen -- so with the toggle off
        // the operator still reached a full working-looking import page, picked
        // a workbook, waited for the upload, and only then learned the feature
        // was switched off. Refusing at the door is the same rule the sidebar
        // and the New Form button now follow.
        if (! feature_enabled('feature_import_enabled')) {
            return redirect()->to(base_url('students/new'))
                ->with('error', 'Excel import is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        return view('students/import_form', [
            'title'     => 'Import Candidates from Excel',
            'maxSizeKb' => StudentImportService::MAX_SIZE_KB,
            'maxBatch'  => StudentVerificationService::MAX_BATCH_SIZE,
        ]);
    }

    /** The blank workbook, with the admission export's own 24 headings. */
    public function importTemplate()
    {
        // Guarded too. The blank workbook is only useful for an import, and it
        // is a direct GET -- reachable by anyone who bookmarked it or kept the
        // tab open, with no upload involved for the service to refuse.
        if (! feature_enabled('feature_import_enabled')) {
            return redirect()->to(base_url('students/new'))
                ->with('error', 'Excel import is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

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
    /**
     * The screen's filters, read once so the list and its export cannot
     * disagree.
     *
     * Exporting used to ignore them entirely: an operator narrowed the screen
     * to one university, pressed Export, and silently got every row in the
     * table. The export link carries the same query string, so reading it in
     * one place is what keeps the file matching what is on screen.
     *
     * trim(), NOT sanitize_xss() -- encoding a search term before binding it
     * corrupts the term itself, and these fields carry apostrophes and
     * ampersands routinely. The query builder binds and the view esc()s.
     *
     * @return array<string,string>
     */
    private function historyFilters(): array
    {
        // Swapped rather than left inverted: from > to matches no row on any
        // of these screens, so the operator would see an empty table with
        // nothing saying why -- and the Export link reuses this same reader.
        [$from, $to] = ordered_date_range(
            trim((string) ($this->request->getGet('date_from') ?? '')),
            trim((string) ($this->request->getGet('date_to') ?? ''))
        );

        return [
            'year'       => trim((string) ($this->request->getGet('year') ?? '')),
            'university' => trim((string) ($this->request->getGet('university') ?? '')),
            'batch'      => trim((string) ($this->request->getGet('batch') ?? '')),
            'course'     => trim((string) ($this->request->getGet('course') ?? '')),
            'name'       => trim((string) ($this->request->getGet('name') ?? '')),
            'date_from'  => $from,
            'date_to'    => $to,
        ];
    }

    /** Batches per page. Small enough that a page stays quick to render. */
    private const HISTORY_PER_PAGE = 20;

    public function history()
    {
        $filters = $this->historyFilters();

        $result = $this->studentService->getBatchSummaries($filters, self::HISTORY_PER_PAGE);

        $data = [
            'title'   => 'Verification Batch History',
            'batches' => $result['batches'],
            'pager'   => $result['pager'],
            'filters' => $filters,
        ];

        // An AJAX request wants the table and its pager, not the whole page.
        // Both are rendered from the SAME partials the full page uses, so a
        // paged or filtered view cannot drift from a freshly loaded one.
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'rows'   => view('students/_history_rows', $data),
                // links('default', 'glass') -- the same call the full page makes,
                // so the AJAX pager is rendered by the identical template
                // rather than a second copy that could drift from it.
                'pager'  => $result['pager'] === null ? '' : $result['pager']->links('default', 'glass'),
                'count'  => count($result['batches']),
                'total'  => $result['pager'] === null ? count($result['batches']) : $result['pager']->getTotal(),
            ]);
        }

        return view('students/history', $data);
    }

    /** Exports exactly what the screen is showing -- same filters, same query string. */
    public function historyExport()
    {
        // null perPage: the export is every matching batch, not one page.
        $batches = $this->studentService->getBatchSummaries($this->historyFilters())['batches'];

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
        $students = $this->studentService->getStudentsByArraySpace($arraySpace);

        $data = [
            'title'      => 'Verification Batch Detail',
            'arraySpace' => $arraySpace,
            'students'   => $students,
        ];

        // Batch History opens this in a dialog rather than navigating, so an
        // AJAX caller gets the candidate rows on their own.
        //
        // Rendered from the SAME partial the full page uses, for the reason
        // that partial was extracted in the first place -- two renderings of
        // one table drift. showActions is off here: the dialog is for reading
        // a batch, and its Edit control never worked (nothing in the codebase
        // binds .edit-student-btn), so carrying it across would be carrying a
        // dead button into a new screen.
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'rows'   => view('students/_batch_rows', [
                    'students'    => $students,
                    'showActions' => false,
                ]),
                'count'  => count($students),
            ]);
        }

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
                // showActions stated rather than left to the partial's default.
                // Config\View::$saveData is true, so view data persists across
                // renders WITHIN a request -- a second render that omits the key
                // inherits whatever the first one set. Nothing renders this
                // twice in one request today; saying it here means nothing has
                // to keep being true for this refresh to keep its buttons.
                'html'      => view('students/_batch_rows', [
                    'students'    => $students,
                    'showActions' => true,
                ]),
                'remaining' => count($students),
            ];
        }

        return $this->respondToPost($ok, $message, null, $extra);
    }
}
