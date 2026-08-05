<?php

namespace App\Controllers;

use App\Services\UniversityService;
use App\Services\ConfirmationService;
use App\Services\AcademicYearService;

class Api extends BaseController
{
    protected UniversityService $universityService;
    protected ConfirmationService $confirmationService;
    protected AcademicYearService $academicYearService;

    public function __construct()
    {
        $this->universityService    = new UniversityService();
        $this->confirmationService = new ConfirmationService();
        $this->academicYearService  = new AcademicYearService();
    }

    public function colleges()
    {
        $q    = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';
        $page = (int) ($this->request->getGet('page') ?? 1);
        // Defaults true (active only) for every "pick a target university"
        // picker; the Universities admin page itself passes active_only=0
        // so a deactivated row can still be found there to be reactivated.
        $activeOnly = $this->request->getGet('active_only') !== '0';

        $data = $this->universityService->searchCollegesForSelect2($q, $page, 20, $activeOnly);
        return $this->response->setJSON($data);
    }

    public function states()
    {
        $q    = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';
        $data = $this->universityService->searchStatesForSelect2($q);
        return $this->response->setJSON($data);
    }

    public function streams()
    {
        $q    = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';
        $data = $this->confirmationService->searchStreamsForSelect2($q);
        return $this->response->setJSON($data);
    }

    public function academicYears()
    {
        $q    = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';
        $data = $this->academicYearService->searchForSelect2($q);
        return $this->response->setJSON($data);
    }
}
