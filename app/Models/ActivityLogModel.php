<?php

namespace App\Models;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table            = 'activity_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['user_id', 'username', 'action', 'entity_type', 'entity_id', 'description', 'created_at'];

    /**
     * A fresh query builder -- see CollegeModel::table() for why this isn't
     * $this->builder() or a chained call on the Model instance itself.
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getRecent(int $limit = 100): array
    {
        return $this->table()->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
    }
}
