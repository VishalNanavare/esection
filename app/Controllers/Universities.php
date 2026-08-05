<?php

namespace App\Controllers;

use App\Services\ExcelExportService;
use App\Services\UniversityService;

class Universities extends BaseController
{
    protected UniversityService $universityService;

    public function __construct()
    {
        $this->universityService = new UniversityService();
    }

    public function index()
    {
        $data = [
            'title'    => 'University Master Directory',
            'colleges' => $this->universityService->getAllColleges(),
            'states'   => $this->universityService->getDistinctStates(),
        ];
        return view('universities/index', $data);
    }

    public function store()
    {
        try {
            $this->universityService->saveUniversity($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('universities'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[Universities::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('universities'))
                ->with('error', 'The university could not be created. The issue has been logged.');
        }

        return redirect()->to(base_url('universities'))->with('success', 'New university created successfully.');
    }

    public function getJson($id)
    {
        $college = $this->universityService->getCollegeById((int)$id);
        if ($college) {
            return $this->response->setJSON(['status' => 'success', 'data' => $college]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'University not found']);
    }

    public function update($id)
    {
        try {
            $this->universityService->updateUniversity((int) $id, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('universities'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[Universities::update] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('universities'))
                ->with('error', 'The university could not be updated. The issue has been logged.');
        }

        return redirect()->to(base_url('universities'))->with('success', 'University details updated successfully.');
    }

    public function toggleActive($id)
    {
        // Belt-and-braces alongside the POST-only route.
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('universities'));
        }

        try {
            $this->universityService->toggleActive((int) $id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('universities'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('universities'))->with('success', 'University status updated.');
    }

    /** No server-side filters on this page -- every university, always. */
    public function export()
    {
        $colleges = $this->universityService->getAllColleges();

        $columns = [
            ['header' => 'Name'],
            ['header' => 'State'],
            ['header' => 'Head Title'],
            ['header' => 'Verification Fees', 'format' => '#,##0.00'],
            ['header' => 'Payment In Favour Of'],
            ['header' => 'Address', 'width' => 40],
            ['header' => 'Status'],
        ];

        $rows = array_map(static function (array $c): array {
            return [
                $c['Name'],
                $c['States'],
                $c['head_name'] ?: 'The Controller of Examinations',
                (float) ($c['fees'] ?: 0),
                $c['in_favour_of'],
                $c['Address'],
                (int) ($c['is_active'] ?? 1) === 1 ? 'Active' : 'Inactive',
            ];
        }, $colleges);

        $summaryLine = 'Filters: none (all universities) | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, 'universities', $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('universities'))->with('error', $e->getMessage());
        }
    }
}
