<?php

namespace App\Controllers;

use App\Models\StudentModel;
use App\Services\ConfirmationService;

class Confirmations extends BaseController
{
    protected ConfirmationService $confirmationService;
    protected StudentModel $studentModel;

    public function __construct()
    {
        $this->confirmationService = new ConfirmationService();
        $this->studentModel      = new StudentModel();
    }

    public function index()
    {
        $selectedYear   = sanitize_xss($this->request->getGet('year') ?? '');
        $selectedStream = sanitize_xss($this->request->getGet('stream') ?? '');

        $students = [];
        if ($selectedYear && $selectedStream) {
            $students = $this->studentModel->getStudentsForConfirmation($selectedYear, $selectedStream);
        }

        $data = [
            'title'           => 'Demand Draft (DD) Payment Confirmation Portal',
            'years'           => $this->studentModel->getDistinctYears(),
            'metrics'         => $this->confirmationService->getStreamMetrics(),
            'students'        => $students,
            'selected_year'   => $selectedYear,
            'selected_stream' => $selectedStream
        ];

        return view('confirmations/index', $data);
    }

    public function store()
    {
        $username = session()->get('username') ?? 'esection1';
        try {
            $res = $this->confirmationService->storeConfirmation($this->request->getPost(), $username);
            return redirect()->to(base_url('confirmations'))->with('success', "DD Payment confirmed for {$res['count']} candidate(s).");
        } catch (\Exception $e) {
            return redirect()->to(base_url('confirmations'))->with('error', $e->getMessage());
        }
    }
}
