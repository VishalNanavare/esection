<?php

namespace App\Services;

use App\Models\CourseModel;

class CourseService
{
    protected CourseModel $courseModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->courseModel        = new CourseModel();
        $this->activityLogService = new ActivityLogService();
    }

    public function getAll(): array
    {
        return $this->courseModel->getAllOrdered();
    }

    /** For future dropdown consumers -- excludes retired courses. */
    public function getActive(): array
    {
        return $this->courseModel->getActiveOrdered();
    }

    public function getById(int $id): ?array
    {
        $res = $this->courseModel->find($id);
        return $res ?: null;
    }

    /**
     * @throws \InvalidArgumentException on bad input
     */
    private function mapData(array $postData, ?int $ignoreId = null): array
    {
        $name = trim(sanitize_xss($postData['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Course name is required.');
        }

        $code = trim(sanitize_xss($postData['code'] ?? ''));
        if ($code !== '') {
            $existing = $this->courseModel->findByCode($code);
            if ($existing && (int) $existing['id'] !== $ignoreId) {
                throw new \InvalidArgumentException('That course code is already in use.');
            }
        }

        return [
            'name' => $name,
            'code' => $code !== '' ? $code : null,
        ];
    }

    public function save(array $postData): void
    {
        $data              = $this->mapData($postData);
        $data['is_active'] = 1;

        $this->courseModel->insert($data);
        $newId = $this->courseModel->getInsertID();

        $this->activityLogService->record('course.create', 'course', $newId, 'Created course ' . $data['name']);
    }

    public function update(int $id, array $postData): void
    {
        $data = $this->mapData($postData, $id);
        $this->courseModel->update($id, $data);

        $this->activityLogService->record('course.update', 'course', $id, 'Updated course ' . $data['name']);
    }

    /**
     * Flip active/inactive. Deliberately no delete(): a course referenced by
     * historical student records must keep showing its name after being
     * retired from new use.
     *
     * @throws \InvalidArgumentException when the course does not exist
     */
    public function toggleActive(int $id): void
    {
        $course = $this->getById($id);
        if (! $course) {
            throw new \InvalidArgumentException('Course not found.');
        }

        $newState = $course['is_active'] ? 0 : 1;
        $this->courseModel->update($id, ['is_active' => $newState]);

        $this->activityLogService->record(
            'course.toggle_active',
            'course',
            $id,
            ($newState ? 'Activated' : 'Deactivated') . ' course ' . $course['name']
        );
    }
}
