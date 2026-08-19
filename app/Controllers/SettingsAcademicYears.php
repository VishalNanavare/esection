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
            'years' => $this->academicYearService->getAll(true),
        ];

        return view('settings/academic_years', $data);
    }

    public function store()
    {
        try {
            $this->academicYearService->save($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAcademicYears::store] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'The academic year could not be created. The issue has been logged.', 500);
        }

        return $this->respond(true, 'Academic year created successfully.');
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
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAcademicYears::update] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'The academic year could not be updated. The issue has been logged.', 500);
        }

        return $this->respond(true, 'Academic year updated successfully.');
    }

    public function setCurrent($id)
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('settings/academic-years'));
        }

        try {
            $this->academicYearService->setCurrent((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAcademicYears::setCurrent] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'Could not set the current academic year. The issue has been logged.', 500);
        }

        return $this->respond(true, 'Current academic year updated.');
    }

    public function delete($id)
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('settings/academic-years'));
        }

        try {
            $this->academicYearService->delete((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            // The arm every sibling on this controller already had. Without it
            // a DB-level failure escaped as an unhandled exception, and the
            // AJAX handler got CodeIgniter's HTML error page where it expects
            // JSON -- so the screen showed a parse failure instead of a reason.
            log_message('error', '[SettingsAcademicYears::delete] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'Could not delete the academic year. The issue has been logged.', 500);
        }

        return $this->respond(true, 'Academic year deleted.');
    }

    /** One reply for every POST on this screen -- see SettingsUsers::respond(). */
    private function respond(bool $ok, string $message, int $failStatus = 422)
    {
        $extra = [];

        if ($this->request->isAJAX()) {
            $extra['html'] = view('settings/_academic_years_rows', ['years' => $this->academicYearService->getAll(true)]);
        }

        return $this->respondToPost($ok, $message, base_url('settings/academic-years'), $extra, $failStatus);
    }
}
