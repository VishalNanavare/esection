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
        $this->universityService->saveUniversity($this->request->getPost());
        return redirect()->to(base_url('universities'))->with('success', 'New University created successfully.');
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
        $this->universityService->updateUniversity((int)$id, $this->request->getPost());
        return redirect()->to(base_url('universities'))->with('success', 'University details updated successfully.');
    }

    public function delete($id)
    {
        $this->universityService->deleteUniversity((int)$id);
        return redirect()->to(base_url('universities'))->with('success', 'University record deleted.');
    }
}
