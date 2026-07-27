<?php

namespace App\Controllers;

use App\Services\StudentVerificationService;
use App\Services\UniversityService;

class Students extends BaseController
{
    protected StudentVerificationService $studentService;
    protected UniversityService $universityService;

    public function __construct()
    {
        $this->studentService    = new StudentVerificationService();
        $this->universityService = new UniversityService();
    }

    public function newForm()
    {
        $data = [
            'title'     => 'New Student Verification Form',
            'common_no' => $this->studentService->getNextCommonNo(),
            'colleges'  => $this->universityService->getAllColleges(),
        ];

        return view('students/new_form', $data);
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
        $json     = $this->request->getJSON(true);
        $username = session()->get('username') ?? 'esection1';

        try {
            $res = $this->studentService->storeCandidateBatch($json ?: [], $username);
            return $this->response->setJSON([
                'status'       => 'success',
                'message'      => "{$res['count']} student verification cases saved successfully.",
                'array_space'  => $res['array_space'],
                'redirect_url' => base_url('pdf/dispatch/' . urlencode($res['array_space']))
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
