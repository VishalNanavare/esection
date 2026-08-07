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
        $q = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';

        // Bounded BEFORE the cast, not after. A bare (int) cast on a numeric
        // string above PHP_INT_MAX raises a PHP 8.5 warning that CodeIgniter
        // promotes to an uncaught ErrorException -- ?page=<21 digits> made
        // this endpoint reply 500 instead of serving Select2 an empty page.
        // filter_var never warns and always lands inside the range, and it
        // also folds the ?page[]= array case back to the default. Every
        // legitimate page number behaves exactly as before.
        $page = (int) filter_var(
            $this->request->getGet('page') ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['default' => 1, 'min_range' => 1, 'max_range' => 100000]]
        );
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
