<?php

namespace App\Services;

use App\Models\StudentModel;

class StudentVerificationService
{
    protected StudentModel $studentModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->studentModel        = new StudentModel();
        $this->activityLogService  = new ActivityLogService();
    }

    public function getNextCommonNo(): int
    {
        return $this->studentModel->getMaxId() + 1;
    }

    public function storeCandidateBatch(array $payload, string $username): array
    {
        if (empty($payload['students']) || !is_array($payload['students'])) {
            throw new \InvalidArgumentException('No valid candidate entries provided in batch payload.');
        }

        $commonNo   = $payload['common_no'] ?? $this->getNextCommonNo();
        $arraySpace = $username . '_' . $commonNo;

        $insertedCount = 0;
        foreach ($payload['students'] as $stud) {
            $data = [
                'to_name'                               => sanitize_xss($payload['to_name'] ?? ''),
                'array_space'                           => $arraySpace,
                'clg_add'                               => sanitize_xss($payload['clg_add'] ?? ''),
                'admission_taken_year'                  => sanitize_xss($payload['admission_taken_year'] ?? ''),
                'student_name'                          => sanitize_xss($stud['student_name'] ?? ''),
                'student_nee_name'                      => sanitize_xss($stud['student_nee_name'] ?? '-'),
                'eligibility_case_no'                   => sanitize_xss($stud['eligibility_case_no'] ?? ''),
                'admission_taken_in'                    => sanitize_xss($payload['admission_taken_in'] ?? ''),
                'verification_of_marksheet_done_by_you' => sanitize_xss($stud['verification_by_you'] ?? ''),
                'in_favour_of'                          => sanitize_xss($payload['in_favour_of'] ?? ''),
                'en_time'                               => time()
            ];

            $this->studentModel->insert($data);
            $insertedCount++;
        }

        return [
            'count'       => $insertedCount,
            'array_space' => $arraySpace
        ];
    }

    public function getStudentsByArraySpace(string $arraySpace): array
    {
        return $this->studentModel->getStudentsByArraySpace($arraySpace);
    }

    public function getStudentById(int $id): ?array
    {
        $res = $this->studentModel->find($id);

        return $res ?: null;
    }

    public function searchStudentsForReminder(?string $year, ?string $stream, ?string $university): array
    {
        return $this->studentModel->getStudentsForReminder($year, $stream, $university);
    }

    /** The pager the last paginated query populated -- read after calling searchStudentsForReminder(). */
    public function getReminderPager(): ?\CodeIgniter\Pager\PagerInterface
    {
        return $this->studentModel->pager;
    }

    /** Same filters as searchStudentsForReminder() above, unpaginated -- feeds the Excel export. */
    public function searchStudentsForReminderAll(?string $year, ?string $stream, ?string $university): array
    {
        return $this->studentModel->getStudentsForReminderAll($year, $stream, $university);
    }

    public function getTotalStudentsCount(): int
    {
        return $this->studentModel->getTotalStudentsCount();
    }

    /** Feeds the batch history/browse page -- mirrors esection_basic's view.php. */
    public function getBatchSummaries(): array
    {
        return $this->studentModel->getBatchSummaries();
    }

    /**
     * Only the 4 fields esection_basic's own update_new_form.php allowed
     * editing -- university/year/course were shown but disabled there too,
     * since changing them would misfile the record into a different batch.
     *
     * @throws \InvalidArgumentException on invalid input or missing record
     */
    public function updateStudent(int $id, array $postData): void
    {
        $student = $this->studentModel->find($id);
        if (! $student) {
            throw new \InvalidArgumentException('Student record not found.');
        }

        $studentName = trim(sanitize_xss($postData['student_name'] ?? ''));
        if ($studentName === '') {
            throw new \InvalidArgumentException('Student name is required.');
        }

        $this->studentModel->update($id, [
            'student_name'                           => $studentName,
            'student_nee_name'                        => sanitize_xss($postData['student_nee_name'] ?? ''),
            'eligibility_case_no'                     => sanitize_xss($postData['eligibility_case_no'] ?? ''),
            'verification_of_marksheet_done_by_you'   => sanitize_xss($postData['verification_of_marksheet_done_by_you'] ?? ''),
        ]);

        $this->activityLogService->record('student.update', 'student', $id, 'Updated candidate ' . $studentName);
    }

    /**
     * Hard delete -- mirrors esection_basic's config/stud-delete.php `?q=` branch.
     *
     * @throws \InvalidArgumentException when the record doesn't exist
     */
    public function deleteStudent(int $id): void
    {
        $student = $this->studentModel->find($id);
        if (! $student) {
            throw new \InvalidArgumentException('Student record not found.');
        }

        $this->studentModel->delete($id);

        $this->activityLogService->record('student.delete', 'student', $id, 'Deleted candidate ' . $student['student_name']);
    }
}
