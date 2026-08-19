<?php

namespace App\Controllers;

use App\Services\UniversityService;
use App\Services\ConfirmationService;
use App\Services\AcademicYearService;
use App\Services\StudentVerificationService;

class Api extends BaseController
{
    protected UniversityService $universityService;
    protected ConfirmationService $confirmationService;
    protected AcademicYearService $academicYearService;
    protected StudentVerificationService $studentService;

    public function __construct()
    {
        $this->universityService    = new UniversityService();
        $this->confirmationService = new ConfirmationService();
        $this->academicYearService  = new AcademicYearService();
        $this->studentService       = new StudentVerificationService();
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

    /**
     * The three Batch History pickers.
     *
     * Separate from streams/academicYears above because they answer a different
     * question: not "what may be chosen when creating a record" but "what is
     * there to be found in the records that exist". For courses those two
     * answers currently share no value at all -- stream_details holds "BCOM"
     * while every batch stores "F.Y.B.Com" -- so pointing this filter at
     * api/streams would have offered 30 options that between them match no
     * batch whatsoever.
     */
    public function batchFilterOptions(string $field)
    {
        $q = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';

        // Bounded before the cast, exactly as colleges() does -- a numeric
        // string above PHP_INT_MAX makes a bare (int) cast raise a PHP 8.5
        // warning that CodeIgniter promotes to an uncaught ErrorException.
        $page = (int) filter_var(
            $this->request->getGet('page') ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['default' => 1, 'min_range' => 1, 'max_range' => 100000]]
        );

        return $this->response->setJSON(
            $this->studentService->filterOptionsForSelect2($field, $q, $page)
        );
    }

    public function academicYears()
    {
        $q    = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';
        $data = $this->academicYearService->searchForSelect2($q);
        return $this->response->setJSON($data);
    }
}
