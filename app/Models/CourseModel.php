<?php

namespace App\Models;

use CodeIgniter\Model;

class CourseModel extends Model
{
    protected $table            = 'courses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'code', 'is_active', 'created_at', 'updated_at'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    /**
     * A fresh query builder -- see CollegeModel::table() for why this isn't
     * $this->builder() or a chained call on the Model instance itself.
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getAllOrdered(): array
    {
        return $this->table()->orderBy('name', 'ASC')->get()->getResultArray();
    }

    /** For future dropdown consumers -- excludes retired courses. */
    public function getActiveOrdered(): array
    {
        return $this->table()->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();
    }

    public function findByCode(string $code): ?array
    {
        $row = $this->table()->where('code', $code)->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * Select2-shaped search, mirroring StreamModel::searchStreamsForSelect2()
     * -- the exact shape needed to become a drop-in replacement for it
     * behind ConfirmationService::searchStreamsForSelect2(). `id` is the
     * course name itself, not the numeric id: every consumer (Reminders,
     * Regularization, Confirmations, Students new-entry) filters or saves
     * against a free-text column, not a foreign key. Active courses only --
     * a retired course should disappear from these pickers.
     */
    public function searchForSelect2(?string $term): array
    {
        $builder = $this->table()->select('name')->where('is_active', 1);

        if (!empty($term)) {
            $builder->like('name', like_term($term));
        }

        $rows = $builder->distinct()->orderBy('name', 'ASC')->get()->getResultArray();

        $results = [];
        foreach ($rows as $r) {
            $name = $r['name'];
            if ($name !== '') {
                $results[] = ['id' => $name, 'text' => $name];
            }
        }

        return ['results' => $results];
    }
}
