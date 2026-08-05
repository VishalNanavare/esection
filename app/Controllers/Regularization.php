<?php

namespace App\Controllers;

use App\Models\StreamModel;
use App\Services\ExcelExportService;
use App\Services\UniversityService;
use App\Services\PdfGenerationService;
use App\Services\RegularizationService;

class Regularization extends BaseController
{
    protected StreamModel $streamModel;
    protected UniversityService $universityService;
    protected PdfGenerationService $pdfService;
    protected RegularizationService $regularizationService;

    public function __construct()
    {
        $this->streamModel           = new StreamModel();
        $this->universityService     = new UniversityService();
        $this->pdfService             = new PdfGenerationService();
        $this->regularizationService = new RegularizationService();
    }

    public function index()
    {
        $data = [
            'title'    => 'Student Eligibility Regularization Portal',
            'streams'  => $this->streamModel->getAllStreams(),
            'colleges' => $this->universityService->getAllColleges(),
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
            return redirect()->to(base_url('regularization'))->with('error', $e->getMessage());
        }

        $record = $this->regularizationService->getById($newId);
        $data   = $this->regularizationService->buildLetterData($record);

        return $this->pdfService->renderPdfResponse('pdf/regularization_letter', $data, 'regularization_letter.pdf');
    }

    /** Browse previously generated letters -- mirrors esection_basic's reg_view.php. */
    public function history()
    {
        $data = [
            'title'    => 'Regularization Letter History',
            'records'  => $this->regularizationService->getAll(),
        ];

        return view('regularization/history', $data);
    }

    /** No server-side filters on this page -- every letter, always. */
    public function historyExport()
    {
        $records = $this->regularizationService->getAll();

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
            return redirect()->to(base_url('regularization/history'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('regularization/history'))->with('success', 'Regularization letter updated.');
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
            return redirect()->to(base_url('regularization/history'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('regularization/history'))->with('success', 'Regularization letter deleted.');
    }
}
