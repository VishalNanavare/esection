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

    /**
     * Reminder batches, newest first, every filter optional.
     *
     * @param array<string,string> $filters university, year, course, date_from, date_to
     */
    public function getAllOrdered(array $filters = []): array
    {
        $builder = $this->table();

        if (($filters['university'] ?? '') !== '') {
            $builder->like('university_name', like_term($filters['university']));
        }

        if (($filters['year'] ?? '') !== '') {
            $builder->where('academic_year', $filters['year']);
        }

        if (($filters['course'] ?? '') !== '') {
            $builder->like('admission_taken_in', like_term($filters['course']));
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
