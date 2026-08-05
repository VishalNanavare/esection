<?php

namespace App\Services;

use App\Models\AcademicYearModel;

class AcademicYearService
{
    protected AcademicYearModel $academicYearModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->academicYearModel  = new AcademicYearModel();
        $this->activityLogService = new ActivityLogService();
    }

    public function getAll(): array
    {
        return $this->academicYearModel->getAllOrdered();
    }

    public function getById(int $id): ?array
    {
        $res = $this->academicYearModel->find($id);
        return $res ?: null;
    }

    public function searchForSelect2(?string $term): array
    {
        return $this->academicYearModel->searchForSelect2($term);
    }

    /**
     * @throws \InvalidArgumentException when the label is missing or already used
     */
    private function mapData(array $postData, ?int $ignoreId = null): array
    {
        $label = trim(sanitize_xss($postData['year_label'] ?? ''));
        if ($label === '') {
            throw new \InvalidArgumentException('Academic year label is required.');
        }

        $existing = $this->academicYearModel->findByLabel($label);
        if ($existing && (int) $existing['id'] !== $ignoreId) {
            throw new \InvalidArgumentException('That academic year already exists.');
        }

        return [
            'year_label' => $label,
            'start_date' => trim((string) ($postData['start_date'] ?? '')) ?: null,
            'end_date'   => trim((string) ($postData['end_date'] ?? '')) ?: null,
        ];
    }

    public function save(array $postData): void
    {
        $data  = $this->mapData($postData);
        $newId = $this->academicYearModel->insertYear($data, ! empty($postData['is_current']));

        $this->activityLogService->record('academic_year.create', 'academic_year', $newId, 'Created academic year ' . $data['year_label']);
    }

    public function update(int $id, array $postData): void
    {
        $data = $this->mapData($postData, $id);
        $this->academicYearModel->updateYear($id, $data, ! empty($postData['is_current']));

        $this->activityLogService->record('academic_year.update', 'academic_year', $id, 'Updated academic year ' . $data['year_label']);
    }

    public function setCurrent(int $id): void
    {
        $year = $this->getById($id);
        if (! $year) {
            throw new \InvalidArgumentException('Academic year not found.');
        }

        $this->academicYearModel->markCurrent($id);

        $this->activityLogService->record('academic_year.set_current', 'academic_year', $id, 'Marked ' . $year['year_label'] . ' as the current academic year');
    }

    public function delete(int $id): void
    {
        if (! feature_enabled('feature_delete_enabled')) {
            throw new \InvalidArgumentException('Deleting records is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        $year = $this->getById($id);
        $this->academicYearModel->delete($id);

        $this->activityLogService->record('academic_year.delete', 'academic_year', $id, 'Deleted academic year ' . ($year['year_label'] ?? (string) $id));
    }
}
