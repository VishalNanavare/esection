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

    public function saveUniversity(array $postData): bool
    {
        $data = [
            'Name'         => sanitize_xss($postData['name'] ?? ''),
            'States'       => sanitize_xss($postData['state'] ?? ''),
            'Address'      => sanitize_xss($postData['address'] ?? ''),
            'email_id'     => sanitize_xss($postData['email_id'] ?? ''),
            'mobile_no'    => sanitize_xss($postData['mobile_no'] ?? ''),
            'fees'         => sanitize_xss($postData['fees'] ?? '0'),
            'head_name'    => sanitize_xss($postData['head_name'] ?? 'The Controller of Examinations'),
            'in_favour_of' => sanitize_xss($postData['in_favour_of'] ?? ''),
            'sel_data'     => '1'
        ];

        return (bool) $this->collegeModel->insert($data);
    }

    public function updateUniversity(int $id, array $postData): bool
    {
        $data = [
            'Name'         => sanitize_xss($postData['name'] ?? ''),
            'States'       => sanitize_xss($postData['state'] ?? ''),
            'Address'      => sanitize_xss($postData['address'] ?? ''),
            'email_id'     => sanitize_xss($postData['email_id'] ?? ''),
            'mobile_no'    => sanitize_xss($postData['mobile_no'] ?? ''),
            'fees'         => sanitize_xss($postData['fees'] ?? '0'),
            'head_name'    => sanitize_xss($postData['head_name'] ?? 'The Controller of Examinations'),
            'in_favour_of' => sanitize_xss($postData['in_favour_of'] ?? ''),
        ];

        return (bool) $this->collegeModel->update($id, $data);
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
