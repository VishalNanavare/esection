<?php

namespace App\Services;

use App\Models\RegularizationModel;

class RegularizationService
{
    protected RegularizationModel $regularizationModel;
    protected LetterTemplateService $letterTemplateService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->regularizationModel   = new RegularizationModel();
        $this->letterTemplateService = new LetterTemplateService();
        $this->activityLogService    = new ActivityLogService();
    }

    /** @param array<string,string> $filters see RegularizationModel::getAllOrdered() */
    public function getAll(array $filters = []): array
    {
        return $this->regularizationModel->getAllOrdered($filters);
    }

    public function getById(int $id): ?array
    {
        $res = $this->regularizationModel->find($id);

        return $res ?: null;
    }

    /**
     * @throws \InvalidArgumentException on invalid input
     */
    private function mapData(array $postData): array
    {
        $studentName = trim(sanitize_xss($postData['student_name'] ?? ''));
        if ($studentName === '') {
            throw new \InvalidArgumentException('Student name is required.');
        }

        return [
            'gender'                 => sanitize_xss($postData['gender'] ?? ''),
            'student_name'           => $studentName,
            'eligibility_case_no'    => sanitize_xss($postData['eligibility_case_no'] ?? ''),
            'admission_letter_for'   => sanitize_xss($postData['admission_letter_for'] ?? ''),
            'admission_letter_date'  => sanitize_xss($postData['admission_letter_date'] ?? '') ?: null,
            'admission_taken_year'   => sanitize_xss($postData['admission_taken_year'] ?? ''),
            'admission_taken_in'     => sanitize_xss($postData['admission_taken_in'] ?? ''),
            'university_name'        => sanitize_xss($postData['clg_add'] ?? ''),
            'passing_course'         => sanitize_xss($postData['admission_passing_course'] ?? ''),
        ];
    }

    /**
     * @throws \InvalidArgumentException on invalid input
     */
    public function create(array $postData, string $username): int
    {
        $data = $this->mapData($postData);
        $data['created_by'] = $username;

        $this->regularizationModel->insert($data);
        $newId = $this->regularizationModel->getInsertID();

        $this->activityLogService->record('regularization.create', 'regularization', $newId, 'Created regularization letter for ' . $data['student_name']);

        return $newId;
    }

    /**
     * @throws \InvalidArgumentException on invalid input or missing record
     */
    public function update(int $id, array $postData): void
    {
        if (! $this->getById($id)) {
            throw new \InvalidArgumentException('Regularization record not found.');
        }

        $data = $this->mapData($postData);
        $this->regularizationModel->update($id, $data);

        $this->activityLogService->record('regularization.update', 'regularization', $id, 'Updated regularization letter for ' . $data['student_name']);
    }

    /**
     * Hard delete -- mirrors esection_basic's config/reg_delete.php, the
     * only hard-delete in the legacy app. No FK elsewhere references this
     * table, so nothing else needs to be kept in sync.
     *
     * @throws \InvalidArgumentException when the record doesn't exist
     */
    public function delete(int $id): void
    {
        if (! feature_enabled('feature_delete_enabled')) {
            throw new \InvalidArgumentException('Deleting records is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        $record = $this->getById($id);
        if (! $record) {
            throw new \InvalidArgumentException('Regularization record not found.');
        }

        $this->regularizationModel->delete($id);

        $this->activityLogService->record('regularization.delete', 'regularization', $id, 'Deleted regularization letter for ' . $record['student_name']);
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
            'regularization',
            $this->letterTemplateService->getFields('regularization'),
            [
                'student_name'        => $record['student_name'],
                'eligibility_case_no' => $record['eligibility_case_no'] ?? '',
                'passing_course'      => $record['passing_course'] ?? '',
            ]
        );

        return array_merge($rendered, [
            'admission_letter_for'  => $record['admission_letter_for'] ?? '',
            'admission_letter_date' => $record['admission_letter_date'] ?? '',
            'passing_course'        => $record['passing_course'] ?? '',
            'university_name'       => $record['university_name'] ?? '',
            'student_name'          => $record['student_name'],
            'eligibility_case_no'   => $record['eligibility_case_no'] ?? '',
            'date'                  => date('d/m/Y'),
        ]);
    }
}
