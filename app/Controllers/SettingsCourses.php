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
            return redirect()->to(base_url('settings/courses'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsCourses::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/courses'))
                ->with('error', 'The course could not be created. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/courses'))->with('success', 'Course created successfully.');
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
            return redirect()->to(base_url('settings/courses'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsCourses::update] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/courses'))
                ->with('error', 'The course could not be updated. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/courses'))->with('success', 'Course updated successfully.');
    }

    public function toggleActive($id)
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('settings/courses'));
        }

        try {
            $this->courseService->toggleActive((int) $id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/courses'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('settings/courses'))->with('success', 'Course status updated.');
    }
}
