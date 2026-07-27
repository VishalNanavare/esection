<?php

namespace App\Services;

use App\Models\CollegeModel;

class UniversityService
{
    protected CollegeModel $collegeModel;

    public function __construct()
    {
        helper('esection');
        $this->collegeModel = new CollegeModel();
    }

    public function getAllColleges(): array
    {
        return $this->collegeModel->getAllColleges();
    }

    public function getDistinctStates(): array
    {
        return $this->collegeModel->getDistinctStates();
    }

    public function getCollegeById(int $id): ?array
    {
        $res = $this->collegeModel->find($id);
        return $res ?: null;
    }

    public function searchCollegesForSelect2(?string $term, int $page = 1, int $limit = 20): array
    {
        return $this->collegeModel->searchColleges($term ?? '', $page, $limit);
    }

    public function searchStatesForSelect2(?string $term): array
    {
        return $this->collegeModel->searchStates($term ?? '');
    }

    /**
     * Map and validate the university form payload.
     *
     * A university with no name is meaningless: it renders as an unlabelled
     * option in every Select2 dropdown and cannot be searched for. The form
     * previously had no server-side check at all, which is how the existing
     * empty row (id 458) came to be.
     *
     * @throws \InvalidArgumentException when required fields are missing
     */
    private function mapUniversityData(array $postData): array
    {
        $name = trim(sanitize_xss($postData['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('University name is required.');
        }

        return [
            'Name'         => $name,
            'States'       => trim(sanitize_xss($postData['state'] ?? '')),
            'Address'      => sanitize_xss($postData['address'] ?? ''),
            'email_id'     => sanitize_xss($postData['email_id'] ?? ''),
            'mobile_no'    => sanitize_xss($postData['mobile_no'] ?? ''),
            'fees'         => sanitize_xss($postData['fees'] ?? '0'),
            'head_name'    => sanitize_xss($postData['head_name'] ?? 'The Controller of Examinations'),
            'in_favour_of' => sanitize_xss($postData['in_favour_of'] ?? ''),
        ];
    }

    public function saveUniversity(array $postData): bool
    {
        $data = $this->mapUniversityData($postData);
        $data['sel_data'] = '1';

        return (bool) $this->collegeModel->insert($data);
    }

    public function updateUniversity(int $id, array $postData): bool
    {
        return (bool) $this->collegeModel->update($id, $this->mapUniversityData($postData));
    }

    public function deleteUniversity(int $id): bool
    {
        return (bool) $this->collegeModel->delete($id);
    }

    public function getTotalCollegesCount(): int
    {
        return $this->collegeModel->getTotalCollegesCount();
    }
}
