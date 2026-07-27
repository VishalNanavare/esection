<?php

namespace App\Controllers;

use App\Services\UniversityService;
use App\Services\ConfirmationService;

class Api extends BaseController
{
    protected UniversityService $universityService;
    protected ConfirmationService $confirmationService;

    public function __construct()
    {
        $this->universityService    = new UniversityService();
        $this->confirmationService = new ConfirmationService();
    }

    public function colleges()
    {
        $q    = $this->request->getGet('q') ?? $this->request->getGet('term') ?? '';
        $page = (int) ($this->request->getGet('page') ?? 1);

        $data = $this->universityService->searchCollegesForSelect2($q, $page);
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
}
