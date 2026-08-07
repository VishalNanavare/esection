<?php

namespace App\Services;

use App\Models\CollegeModel;
use App\Models\StudentModel;

class UniversityService
{
    protected CollegeModel $collegeModel;
    protected StudentModel $studentModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->collegeModel       = new CollegeModel();
        $this->studentModel       = new StudentModel();
        $this->activityLogService = new ActivityLogService();
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

    public function getCollegeByAddress(string $address): ?array
    {
        return $this->collegeModel->findByAddress($address);
    }

    public function searchCollegesForSelect2(?string $term, int $page = 1, int $limit = 20, bool $activeOnly = true): array
    {
        return $this->collegeModel->searchColleges($term ?? '', $page, $limit, $activeOnly);
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

        $result = (bool) $this->collegeModel->insert($data);
        $this->activityLogService->record('university.create', 'university', $this->collegeModel->getInsertID(), 'Created university ' . $data['Name']);

        return $result;
    }

    public function updateUniversity(int $id, array $postData): bool
    {
        $existing = $this->getCollegeById($id);
        $oldName  = $existing['Name'] ?? '';

        // mapUniversityData() validates and throws BEFORE anything is
        // written, so it stays outside the transaction.
        $data = $this->mapUniversityData($postData);

        // A rename is two writes against two tables -- college_details here,
        // and the cascade across student_details below. They were previously
        // independent, so a failure between them left the directory renamed
        // while thousands of historical student rows still carried the old
        // name. Because student_details stores the university as a plain
        // string (not an FK), those records would then be permanently
        // unmatchable by the university filter, with nothing to indicate it.
        // One transaction makes the rename all-or-nothing.
        $db = \Config\Database::connect();
        $db->transStart();

        $result = (bool) $this->collegeModel->update($id, $data);

        $this->activityLogService->record('university.update', 'university', $id, 'Updated university ' . $data['Name']);

        // Mirrors esection_basic's clg_edit.php: student_details stores the
        // university name as a plain string at entry time, not a foreign
        // key, so a rename must be propagated or historical records go
        // stale. Logged separately (legacy did this silently) since
        // rewriting other tables' historical data is worth an audit trail.
        if ($oldName !== '' && $oldName !== $data['Name']) {
            $renamed = $this->studentModel->renameCollegeReferences($oldName, $data['Name']);

            if ($renamed > 0) {
                $this->activityLogService->record(
                    'university.rename_cascade',
                    'student_details',
                    null,
                    "Renamed \"{$oldName}\" to \"{$data['Name']}\" across {$renamed} student record(s)"
                );
            }
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \RuntimeException('The university could not be updated. No changes were saved.');
        }

        return $result;
    }

    /**
     * Flip active/inactive. Deliberately no delete(): student_details keeps
     * a copied name/address at creation time rather than a foreign key to
     * this table, so a deleted university's name would vanish from every
     * historical record that referenced it. Deactivating avoids that.
     *
     * @throws \InvalidArgumentException when the university does not exist
     */
    public function toggleActive(int $id): void
    {
        $college = $this->getCollegeById($id);
        if (! $college) {
            throw new \InvalidArgumentException('University not found.');
        }

        $newState = $college['is_active'] ? 0 : 1;
        $this->collegeModel->update($id, ['is_active' => $newState]);

        $this->activityLogService->record(
            'university.toggle_active',
            'university',
            $id,
            ($newState ? 'Activated' : 'Deactivated') . ' university ' . $college['Name']
        );
    }

    public function getTotalCollegesCount(): int
    {
        return $this->collegeModel->getTotalCollegesCount();
    }
}
