<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentReminderModel extends Model
{
    protected $table            = 'student_reminders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'student_name', 'eligibility_case_no', 'course_name', 'missing_doc',
        'created_by', 'created_at',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';

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
