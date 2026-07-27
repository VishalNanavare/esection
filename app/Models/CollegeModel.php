<?php

namespace App\Models;

use CodeIgniter\Model;

class CollegeModel extends Model
{
    protected $table            = 'college_details';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'Name', 'States', 'Address', 'email_id',
        'mobile_no', 'fees', 'head_name', 'in_favour_of', 'sel_data'
    ];

    public function getAllColleges(): array
    {
        return $this->orderBy('Name', 'ASC')->findAll();
    }

    public function getDistinctStates(): array
    {
        return $this->select('States')
                    ->distinct()
                    ->where('States IS NOT NULL')
                    ->where('States !=', '')
                    ->orderBy('States', 'ASC')
                    ->findAll();
    }

    public function searchColleges(string $term = '', int $page = 1, int $limit = 20): array
    {
        $builder = $this->builder();
        if (!empty($term)) {
            $builder->groupStart()
                    ->like('Name', $term)
                    ->orLike('States', $term)
                    ->groupEnd();
        }

        $offset = ($page - 1) * $limit;
        $colleges = $builder->orderBy('Name', 'ASC')->limit($limit, $offset)->get()->getResultArray();

        $results = [];
        foreach ($colleges as $c) {
            $results[] = [
                'id'           => $c['id'],
                'text'         => $c['Name'] . ' (' . ($c['States'] ?: 'India') . ')',
                'name'         => $c['Name'],
                'state'        => $c['States'],
                'address'      => $c['Address'],
                'in_favour_of' => $c['in_favour_of'],
                'head_name'    => $c['head_name'],
                'fees'         => $c['fees'],
            ];
        }

        return [
            'results'    => $results,
            'pagination' => ['more' => count($results) === $limit]
        ];
    }

    public function searchStates(string $term = ''): array
    {
        $states = $this->getDistinctStates();
        $results = [];
        foreach ($states as $st) {
            $name = $st['States'];
            if (empty($term) || stripos($name, $term) !== false) {
                $results[] = [
                    'id'   => $name,
                    'text' => $name
                ];
            }
        }
        return ['results' => $results];
    }

    public function getTotalCollegesCount(): int
    {
        return $this->countAllResults();
    }
}
