<?php

namespace App\Controllers;

use App\Services\StudentVerificationService;
use App\Services\ConfirmationService;
use App\Services\UniversityService;

class Dashboard extends BaseController
{
    protected StudentVerificationService $studentService;
    protected ConfirmationService $confirmationService;
    protected UniversityService $universityService;

    public function __construct()
    {
        $this->studentService    = new StudentVerificationService();
        $this->confirmationService = new ConfirmationService();
        $this->universityService = new UniversityService();
    }

    public function index()
    {
        $totalStudents  = $this->studentService->getTotalStudentsCount();
        $totalConfirmed = $this->confirmationService->getTotalConfirmedCount();
        $totalColleges  = $this->universityService->getTotalCollegesCount();
        $totalPending   = max(0, $totalStudents - $totalConfirmed);
        $metrics        = $this->confirmationService->getStreamMetrics();

        $data = [
            'title'           => 'IDOL E-Section Dashboard',
            'total_students'  => $totalStudents,
            'total_confirmed' => $totalConfirmed,
            'total_pending'   => $totalPending,
            'total_colleges'  => $totalColleges,
            'metrics'         => $metrics,
        ];

        return view('dashboard/index', $data);
    }
}
