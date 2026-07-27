<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table            = 'student_details';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'array_space', 'to_name', 'clg_add', 'admission_taken_year',
        'student_name', 'student_nee_name', 'eligibility_case_no',
        'admission_taken_in', 'verification_of_marksheet_done_by_you',
        'in_favour_of', 'en_time'
    ];

    public function getMaxId(): int
    {
        $query = $this->builder()->selectMax('id')->get()->getRowArray();
        return $query ? (int) $query['id'] : 0;
    }

    public function getTotalStudentsCount(): int
    {
        return $this->builder()->countAllResults();
    }

    public function getStudentsByArraySpace(string $arraySpace): array
    {
        return $this->builder()->where('array_space', $arraySpace)->get()->getResultArray();
    }

    public function getDistinctYears(): array
    {
        return $this->builder()
                    ->select('admission_taken_year')
                    ->distinct()
                    ->where('admission_taken_year IS NOT NULL')
                    ->where('admission_taken_year !=', '')
                    ->orderBy('admission_taken_year', 'DESC')
                    ->get()->getResultArray();
    }

    public function getDistinctStreamsFromStudents(): array
    {
        return $this->builder()
                    ->select('admission_taken_in')
                    ->distinct()
                    ->where('admission_taken_in IS NOT NULL')
                    ->where('admission_taken_in !=', '')
                    ->orderBy('admission_taken_in', 'ASC')
                    ->get()->getResultArray();
    }

    private function getConfJoinClause(): string
    {
        if ($this->db->fieldExists('student_id', 'conf_stud_data')) {
            return 'conf_stud_data.student_id = student_details.id';
        }
        if ($this->db->fieldExists('case_no', 'conf_stud_data')) {
            return 'conf_stud_data.case_no = student_details.eligibility_case_no';
        }
        return 'conf_stud_data.id = student_details.id';
    }

    public function getGroupedStudentCounts(): array
    {
        $rows = $this->builder()
                     ->select('admission_taken_in, COUNT(*) as cnt')
                     ->where('admission_taken_in IS NOT NULL')
                     ->where('admission_taken_in !=', '')
                     ->groupBy('admission_taken_in')
                     ->get()->getResultArray();

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['admission_taken_in']] = (int) $r['cnt'];
        }
        return $counts;
    }

    public function getGroupedConfirmedCounts(): array
    {
        $joinClause = $this->getConfJoinClause();
        $rows = $this->builder()
                     ->select('student_details.admission_taken_in, COUNT(DISTINCT student_details.id) as cnt')
                     ->join('conf_stud_data', $joinClause, 'inner')
                     ->where('student_details.admission_taken_in IS NOT NULL')
                     ->where('student_details.admission_taken_in !=', '')
                     ->groupBy('student_details.admission_taken_in')
                     ->get()->getResultArray();

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['admission_taken_in']] = (int) $r['cnt'];
        }
        return $counts;
    }

    public function getStudentsForConfirmation(string $year, string $stream): array
    {
        $joinClause = $this->getConfJoinClause();
        $builder    = $this->builder();
        $selectCols = 'student_details.*';

        if ($this->db->fieldExists('dd_no', 'conf_stud_data')) {
            $selectCols .= ', conf_stud_data.dd_no';
        }
        if ($this->db->fieldExists('bank_name', 'conf_stud_data')) {
            $selectCols .= ', conf_stud_data.bank_name';
        }
        if ($this->db->fieldExists('dd_date', 'conf_stud_data')) {
            $selectCols .= ', conf_stud_data.dd_date';
        }
        if ($this->db->fieldExists('dd_amount', 'conf_stud_data')) {
            $selectCols .= ', conf_stud_data.dd_amount';
        }

        return $builder->select($selectCols)
                       ->join('conf_stud_data', $joinClause, 'left')
                       ->where('student_details.admission_taken_year', $year)
                       ->like('student_details.admission_taken_in', $stream)
                       ->orderBy('student_details.id', 'ASC')
                       ->get()->getResultArray();
    }

    public function getStudentsForReminder(?string $year, ?string $stream, ?string $university): array
    {
        if (empty($year) || empty($stream)) {
            return [];
        }

        $builder = $this->builder()
                        ->where('admission_taken_year', $year)
                        ->like('admission_taken_in', $stream);

        if (!empty($university)) {
            $builder->like('clg_add', $university);
        }

        return $builder->orderBy('id', 'ASC')->get()->getResultArray();
    }

    public function getStudentsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->builder()->whereIn('id', array_map('intval', $ids))->get()->getResultArray();
    }
}
