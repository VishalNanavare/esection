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
        // trim(), NOT sanitize_xss() -- see Confirmations::history().
        $filters = [
            'name'  => trim((string) ($this->request->getGet('name') ?? '')),
            'state' => trim((string) ($this->request->getGet('state') ?? '')),
        ];

        $data = [
            'title'    => 'University Master Directory',
            'colleges' => $this->universityService->getAllColleges($filters),
            'filters'  => $filters,
            'states'   => $this->universityService->getDistinctStates(),
            // 'states' removed: universities/index.php renders the static
            // common/india_states_options partial, not $states. The service
            // method stays -- CollegeModel::searchStates() backs /api/states.
        ];
        return view('universities/index', $data);
    }

    public function store()
    {
        try {
            $this->universityService->saveUniversity($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[Universities::store] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'The university could not be created. The issue has been logged.', 500);
        }

        return $this->respond(true, 'New university created successfully.');
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
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[Universities::update] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'The university could not be updated. The issue has been logged.', 500);
        }

        return $this->respond(true, 'University details updated successfully.');
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
            return $this->respond(false, $e->getMessage());
        }

        return $this->respond(true, 'University status updated.');
    }

    /**
     * One reply for every POST on this screen.
     *
     * The rows carry the data-state / data-id attributes the client-side filter
     * matches on, so they must come from the same partial the page used --
     * ajax_universities_js.php re-runs its filter against them afterwards.
     */
    private function respond(bool $ok, string $message, int $failStatus = 422)
    {
        $extra = [];

        if ($this->request->isAJAX()) {
            $extra['html'] = view('universities/_rows', [
                'colleges' => $this->universityService->getAllColleges(),
            ]);
        }

        return $this->respondToPost($ok, $message, base_url('universities'), $extra, $failStatus);
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
