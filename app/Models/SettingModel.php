<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['setting_key', 'setting_value', 'setting_group', 'updated_by', 'updated_at'];

    /**
     * A fresh query builder -- see CollegeModel::table() for why this isn't
     * $this->builder() or a chained call on the Model instance itself.
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function findByKey(string $key): ?array
    {
        $row = $this->table()->where('setting_key', $key)->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * @param string[] $keys
     * @return array<string, array> rows indexed by setting_key
     */
    public function findByKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $rows = $this->table()->whereIn('setting_key', $keys)->get()->getResultArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['setting_key']] = $row;
        }

        return $indexed;
    }
}
