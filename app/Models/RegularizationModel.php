<?php

namespace App\Models;

use CodeIgniter\Model;

class RegularizationModel extends Model
{
    protected $table            = 'regularizations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'gender', 'student_name', 'eligibility_case_no', 'admission_letter_for',
        'admission_letter_date', 'admission_taken_year', 'admission_taken_in',
        'university_name', 'passing_course', 'created_by', 'created_at', 'updated_at',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    /**
     * A fresh query builder. Not Model::builder() and not the Model's own
     * chainable methods -- both accumulate state on a shared instance, which
     * leaks between calls (same reasoning as CollegeModel/AcademicYearModel).
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    /**
     * Regularization letters, newest first, every filter optional.
     *
     * created_at is a real datetime here, so the date range compares against
     * the day's boundaries directly.
     *
     * @param array<string,string> $filters name, case_no, university, year, date_from, date_to
     */
    public function getAllOrdered(array $filters = []): array
    {
        $builder = $this->table();

        if (($filters['name'] ?? '') !== '') {
            $builder->like('student_name', like_term($filters['name']));
        }

        if (($filters['case_no'] ?? '') !== '') {
            $builder->like('eligibility_case_no', like_term($filters['case_no']));
        }

        if (($filters['university'] ?? '') !== '') {
            $builder->like('university_name', like_term($filters['university']));
        }

        if (($filters['year'] ?? '') !== '') {
            $builder->where('admission_taken_year', $filters['year']);
        }

        if (($filters['date_from'] ?? '') !== '') {
            $builder->where('created_at >=', $filters['date_from'] . ' 00:00:00');
        }

        if (($filters['date_to'] ?? '') !== '') {
            $builder->where('created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }
}
