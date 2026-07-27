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
        $username = (string) (session()->get('username') ?? '');

        try {
            $res = $this->confirmationService->storeConfirmation($this->request->getPost(), $username);

            $message = "DD payment confirmed for {$res['count']} candidate(s).";
            if (! empty($res['skipped'])) {
                $message .= " {$res['skipped']} already had a confirmation and were skipped.";
            }

            return redirect()->to(base_url('confirmations'))->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            // Our own validation text -- safe and useful to show the operator.
            return redirect()->to(base_url('confirmations'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Anything else (DatabaseException, etc.) may carry SQL fragments,
            // column names or file paths. Log it; show the user a fixed string.
            log_message('error', '[Confirmations::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('confirmations'))
                ->with('error', 'The DD confirmation could not be saved. The issue has been logged.');
        }
    }
}
