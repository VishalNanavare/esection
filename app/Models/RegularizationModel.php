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

    public function getAllOrdered(): array
    {
        return $this->table()->orderBy('id', 'DESC')->get()->getResultArray();
    }
}
