<?php

namespace App\Controllers;

use App\Services\CourseService;

class SettingsCourses extends BaseController
{
    protected CourseService $courseService;

    public function __construct()
    {
        $this->courseService = new CourseService();
    }

    public function index()
    {
        $data = [
            'title'   => 'Settings — Courses',
            'courses' => $this->courseService->getAll(),
        ];

        return view('settings/courses', $data);
    }

    public function store()
    {
        try {
            $this->courseService->save($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsCourses::store] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'The course could not be created. The issue has been logged.', 500);
        }

        return $this->respond(true, 'Course created successfully.');
    }

    public function getJson($id)
    {
        $course = $this->courseService->getById((int) $id);
        if ($course) {
            return $this->response->setJSON(['status' => 'success', 'data' => $course]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'Course not found']);
    }

    public function update($id)
    {
        try {
            $this->courseService->update((int) $id, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsCourses::update] {message}', ['message' => (string) $e]);

            return $this->respond(false, 'The course could not be updated. The issue has been logged.', 500);
        }

        return $this->respond(true, 'Course updated successfully.');
    }

    public function toggleActive($id)
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('settings/courses'));
        }

        try {
            $this->courseService->toggleActive((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->respond(false, $e->getMessage());
        }

        return $this->respond(true, 'Course status updated.');
    }

    /** One reply for every POST on this screen -- see SettingsUsers::respond(). */
    private function respond(bool $ok, string $message, int $failStatus = 422)
    {
        $extra = [];

        if ($this->request->isAJAX()) {
            $extra['html'] = view('settings/_courses_rows', ['courses' => $this->courseService->getAll()]);
        }

        return $this->respondToPost($ok, $message, base_url('settings/courses'), $extra, $failStatus);
    }
}
