<?php

namespace App\Services;

use App\Models\StudentModel;

class StudentVerificationService
{
    protected StudentModel $studentModel;

    public function __construct()
    {
        helper('esection');
        $this->studentModel = new StudentModel();
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

    public function searchStudentsForReminder(?string $year, ?string $stream, ?string $university): array
    {
        return $this->studentModel->getStudentsForReminder($year, $stream, $university);
    }

    public function getTotalStudentsCount(): int
    {
        return $this->studentModel->getTotalStudentsCount();
    }
}
