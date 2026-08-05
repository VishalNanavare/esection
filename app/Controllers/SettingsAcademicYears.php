<?php

namespace App\Controllers;

use App\Services\AcademicYearService;

class SettingsAcademicYears extends BaseController
{
    protected AcademicYearService $academicYearService;

    public function __construct()
    {
        $this->academicYearService = new AcademicYearService();
    }

    public function index()
    {
        $data = [
            'title' => 'Settings — Academic Years',
            'years' => $this->academicYearService->getAll(),
        ];

        return view('settings/academic_years', $data);
    }

    public function store()
    {
        try {
            $this->academicYearService->save($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/academic-years'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAcademicYears::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/academic-years'))
                ->with('error', 'The academic year could not be created. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/academic-years'))->with('success', 'Academic year created successfully.');
    }

    public function getJson($id)
    {
        $year = $this->academicYearService->getById((int) $id);
        if ($year) {
            return $this->response->setJSON(['status' => 'success', 'data' => $year]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'Academic year not found']);
    }

    public function update($id)
    {
        try {
            $this->academicYearService->update((int) $id, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/academic-years'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAcademicYears::update] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/academic-years'))
                ->with('error', 'The academic year could not be updated. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/academic-years'))->with('success', 'Academic year updated successfully.');
    }

    public function setCurrent($id)
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('settings/academic-years'));
        }

        try {
            $this->academicYearService->setCurrent((int) $id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/academic-years'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAcademicYears::setCurrent] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/academic-years'))
                ->with('error', 'Could not set the current academic year. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/academic-years'))->with('success', 'Current academic year updated.');
    }

    public function delete($id)
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('settings/academic-years'));
        }

        $this->academicYearService->delete((int) $id);

        return redirect()->to(base_url('settings/academic-years'))->with('success', 'Academic year deleted.');
    }
}
