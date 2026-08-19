<?php

namespace App\Controllers;

use App\Services\ExcelExportService;
use App\Services\PdfGenerationService;
use App\Services\RegularizationService;

class Regularization extends BaseController
{
    protected PdfGenerationService $pdfService;
    protected RegularizationService $regularizationService;

    public function __construct()
    {
        $this->pdfService             = new PdfGenerationService();
        $this->regularizationService = new RegularizationService();
    }

    public function index()
    {
        // 'streams' and 'colleges' used to be loaded here and handed to the
        // view, which never read either of them: the screen's two pickers are
        // Select2 widgets that fetch api/streams and api/colleges over AJAX
        // (see common/ajax_regularization_js). Every open of this page was
        // therefore reading the full university register and stream list --
        // 469 and 45 rows -- to discard both.
        $data = [
            'title' => 'Student Eligibility Regularization Portal',
        ];

        return view('regularization/index', $data);
    }

    /**
     * Persists the letter (restoring esection_basic's reg_data behavior,
     * which CI4's rewrite had dropped entirely) and opens the PDF in the
     * same request/new tab, unchanged from today's user-facing behavior.
     */
    public function generateLetter()
    {
        $username = (string) (session()->get('username') ?? '');

        try {
            $newId = $this->regularizationService->create($this->request->getPost(), $username);
        } catch (\InvalidArgumentException $e) {
            // JSON for an XHR caller, not a redirect. jQuery follows a 302
            // transparently and would hand the client an HTML page where it
            // expects JSON -- the operator would see a generic parse failure
            // instead of "Student name is required.", and the flash would sit
            // in the session waiting to surface on some later page load.
            if ($this->request->isAJAX()) {
                return $this->respondToPost(false, $e->getMessage(), base_url('regularization'));
            }

            return redirect()->to(base_url('regularization'))->with('error', $e->getMessage());
        }

        // An XHR cannot become a new tab, so it gets the address of the letter
        // and points its already-open tab at it.
        if ($this->request->isAJAX()) {
            return $this->respondToPost(true, 'Regularization letter created.', base_url('regularization'), [
                'pdf_url' => base_url('regularization/pdf/' . $newId),
            ]);
        }

        // The non-AJAX response IS the document, and must stay that way: this
        // form is target="_blank", so with JavaScript unavailable the browser
        // posts into a new tab and expects the letter back. Routing this
        // through respondToPost's redirect branch would save the record and
        // show a green toast with no letter anywhere.
        $record = $this->regularizationService->getById($newId);
        $data   = $this->regularizationService->buildLetterData($record);

        return $this->pdfService->renderPdfResponse('pdf/regularization_letter', $data, 'regularization_letter.pdf');
    }

    /** Browse previously generated letters -- mirrors esection_basic's reg_view.php. */
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
        return [
            'name'       => trim((string) ($this->request->getGet('name') ?? '')),
            'case_no'    => trim((string) ($this->request->getGet('case_no') ?? '')),
            'university' => trim((string) ($this->request->getGet('university') ?? '')),
            'year'       => trim((string) ($this->request->getGet('year') ?? '')),
            'date_from'  => trim((string) ($this->request->getGet('date_from') ?? '')),
            'date_to'    => trim((string) ($this->request->getGet('date_to') ?? '')),
        ];
    }

    public function history()
    {
        $filters = $this->historyFilters();

        $data = [
            'title'    => 'Regularization Letter History',
            'records'  => $this->regularizationService->getAll($filters),
            'filters'  => $filters,
        ];

        return view('regularization/history', $data);
    }

    /** Exports exactly what the screen is showing -- same filters, same query string. */
    public function historyExport()
    {
        $records = $this->regularizationService->getAll($this->historyFilters());

        $columns = [
            ['header' => 'Student Name'],
            ['header' => 'University / Board', 'width' => 35],
            ['header' => 'Admission Taken In'],
            ['header' => 'Academic Year'],
            ['header' => 'Created', 'format' => 'dd/mm/yyyy hh:mm'],
        ];

        $rows = array_map(static function (array $r): array {
            $created = $r['created_at'] !== null && $r['created_at'] !== ''
                ? \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $r['created_at'])
                : null;

            return [
                $r['student_name'],
                $r['university_name'],
                $r['admission_taken_in'],
                $r['admission_taken_year'],
                $created ?: '-',
            ];
        }, $records);

        $summaryLine = 'Filters: none | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, 'regularization_history', $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('regularization/history'))->with('error', $e->getMessage());
        }
    }

    public function getJson($id)
    {
        $record = $this->regularizationService->getById((int) $id);
        if ($record) {
            return $this->response->setJSON(['status' => 'success', 'data' => $record]);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Record not found']);
    }

    /** Mirrors esection_basic's reg_update.php + config/reg_update.php. */
    public function update($id)
    {
        try {
            $this->regularizationService->update((int) $id, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respondForHistory(false, $e->getMessage());
        }

        return $this->respondForHistory(true, 'Regularization letter updated.');
    }

    /** Reprint an existing letter -- mirrors esection_basic's config/reg_pdf.php. */
    public function pdf($id)
    {
        $record = $this->regularizationService->getById((int) $id);
        if (! $record) {
            return redirect()->to(base_url('regularization/history'))->with('error', 'Record not found.');
        }

        $data = $this->regularizationService->buildLetterData($record);

        return $this->pdfService->renderPdfResponse('pdf/regularization_letter', $data, 'regularization_letter.pdf');
    }

    /**
     * Hard delete -- mirrors esection_basic's config/reg_delete.php, the
     * only hard-delete in the legacy app.
     */
    public function delete($id)
    {
        try {
            $this->regularizationService->delete((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->respondForHistory(false, $e->getMessage());
        }

        return $this->respondForHistory(true, 'Regularization letter deleted.');
    }

    /** One reply for the two history POSTs -- see SettingsUsers::respond(). */
    private function respondForHistory(bool $ok, string $message, int $failStatus = 422)
    {
        $extra = [];

        if ($this->request->isAJAX()) {
            $extra['html'] = view('regularization/_history_rows', [
                'records' => $this->regularizationService->getAll(),
            ]);
        }

        return $this->respondToPost($ok, $message, base_url('regularization/history'), $extra, $failStatus);
    }
}
