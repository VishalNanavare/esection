<?php

namespace App\Models;

use CodeIgniter\Model;

class UniversityReminderBatchModel extends Model
{
    protected $table            = 'university_reminder_batches';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'academic_year', 'university_name', 'admission_taken_in', 'head_name',
        'created_by', 'created_at', 'updated_at',
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

    public function findByYearAndUniversity(string $academicYear, string $universityName): ?array
    {
        $row = $this->table()
                    ->where('academic_year', $academicYear)
                    ->where('university_name', $universityName)
                    ->get()->getRowArray();

        return $row ?: null;
    }

    public function getAllOrdered(): array
    {
        return $this->table()->orderBy('id', 'DESC')->get()->getResultArray();
    }
}
