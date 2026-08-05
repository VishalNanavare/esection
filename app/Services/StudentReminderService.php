<?php

namespace App\Services;

use App\Models\StudentReminderModel;

class StudentReminderService
{
    protected StudentReminderModel $studentReminderModel;
    protected LetterTemplateService $letterTemplateService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->studentReminderModel = new StudentReminderModel();
        $this->letterTemplateService = new LetterTemplateService();
        $this->activityLogService    = new ActivityLogService();
    }

    public function getAll(): array
    {
        return $this->studentReminderModel->getAllOrdered();
    }

    public function getById(int $id): ?array
    {
        $res = $this->studentReminderModel->find($id);

        return $res ?: null;
    }

    /**
     * @throws \InvalidArgumentException on invalid input
     */
    public function create(array $postData, string $username): int
    {
        $studentName = trim(sanitize_xss($postData['student_name'] ?? ''));
        if ($studentName === '') {
            throw new \InvalidArgumentException('Candidate name is required.');
        }

        $data = [
            'student_name'        => $studentName,
            'eligibility_case_no' => sanitize_xss($postData['eligibility_case_no'] ?? ''),
            'course_name'         => sanitize_xss($postData['course_name'] ?? ''),
            'missing_doc'         => sanitize_xss($postData['missing_doc'] ?? ''),
            'created_by'          => $username,
        ];

        $this->studentReminderModel->insert($data);
        $newId = $this->studentReminderModel->getInsertID();

        $this->activityLogService->record('student_reminder.create', 'student_reminder', $newId, 'Created candidate reminder for ' . $studentName);

        return $newId;
    }

    /**
     * @throws \InvalidArgumentException when the record doesn't exist
     */
    public function delete(int $id): void
    {
        if (! feature_enabled('feature_delete_enabled')) {
            throw new \InvalidArgumentException('Deleting records is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        $record = $this->getById($id);
        if (! $record) {
            throw new \InvalidArgumentException('Reminder record not found.');
        }

        $this->studentReminderModel->delete($id);

        $this->activityLogService->record('student_reminder.delete', 'student_reminder', $id, 'Deleted candidate reminder for ' . $record['student_name']);
    }

    /**
     * Builds the $data array PdfGenerationService needs, from EITHER a
     * fresh form submission or a persisted record -- the single place this
     * assembly happens, so a reprint from history can never drift from a
     * freshly-generated letter.
     */
    public function buildLetterData(array $record): array
    {
        $rendered = $this->letterTemplateService->render(
            'student_reminder',
            $this->letterTemplateService->getFields('student_reminder'),
            [
                'course_name' => $record['course_name'] ?? '',
                'missing_doc' => $record['missing_doc'] ?? '',
            ]
        );

        return array_merge($rendered, [
            'student_name'        => $record['student_name'],
            'eligibility_case_no' => $record['eligibility_case_no'] ?? '',
            'course_name'         => $record['course_name'] ?? '',
            'missing_doc'         => $record['missing_doc'] ?? '',
            'date'                => date('d/m/Y'),
        ]);
    }
}
