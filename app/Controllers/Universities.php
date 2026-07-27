<?php

namespace App\Controllers;

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

    public function delete($id)
    {
        // Belt-and-braces alongside the POST-only route.
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('universities'));
        }

        $this->universityService->deleteUniversity((int) $id);

        return redirect()->to(base_url('universities'))->with('success', 'University record deleted.');
    }
}
