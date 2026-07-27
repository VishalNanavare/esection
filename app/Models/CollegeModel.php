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

    /** Select2 page size. */
    private const PAGE_SIZE = 20;

    /**
     * A fresh query builder.
     *
     * Not Model::builder() and not the Model's own chainable methods -- both
     * accumulate state on a shared instance, which leaks between calls.
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getAllColleges(): array
    {
        return $this->table()->orderBy('Name', 'ASC')->get()->getResultArray();
    }

    public function getDistinctStates(): array
    {
        return $this->table()
                    ->select('States')
                    ->distinct()
                    ->where('States IS NOT NULL')
                    ->where('States !=', '')
                    ->orderBy('States', 'ASC')
                    ->get()->getResultArray();
    }

    /**
     * Select2-shaped college search.
     *
     * @return array{results: array<int, array<string, mixed>>, pagination: array{more: bool}}
     */
    public function searchColleges(string $term = '', int $page = 1, int $limit = self::PAGE_SIZE): array
    {
        $page  = max(1, $page);
        $limit = min(max($limit, 1), 100);

        $builder = $this->table();

        if ($term !== '') {
            $builder->groupStart()
                    ->like('Name', $term)
                    ->orLike('States', $term)
                    ->groupEnd();
        }

        $colleges = $builder->orderBy('Name', 'ASC')
                            ->limit($limit, ($page - 1) * $limit)
                            ->get()->getResultArray();

        $results = [];
        foreach ($colleges as $c) {
            $name = (string) ($c['Name'] ?? '');
            $results[] = [
                // NOTE: `id` is the numeric college id and `name` the raw
                // name. Both are consumed: students/new keys off `id`, while
                // regularization and reminders remap `id` to `name` because
                // those forms persist the university name, not its id.
                'id'           => $c['id'],
                'text'         => ($name !== '' ? $name : '(Unnamed)')
                                  . ' (' . (($c['States'] ?? '') ?: 'India') . ')',
                'name'         => $name,
                'state'        => $c['States'] ?? '',
                'address'      => $c['Address'] ?? '',
                'in_favour_of' => $c['in_favour_of'] ?? '',
                'head_name'    => $c['head_name'] ?? '',
                'fees'         => $c['fees'] ?? '',
            ];
        }

        return [
            'results'    => $results,
            'pagination' => ['more' => count($results) === $limit],
        ];
    }

    /**
     * @return array{results: array<int, array{id: string, text: string}>, pagination: array{more: bool}}
     */
    public function searchStates(string $term = ''): array
    {
        $results = [];

        foreach ($this->getDistinctStates() as $st) {
            $name = (string) $st['States'];
            if ($term === '' || stripos($name, $term) !== false) {
                $results[] = ['id' => $name, 'text' => $name];
            }
        }

        // Shape kept identical to searchColleges() so one generic Select2
        // initialiser can drive every endpoint.
        return ['results' => $results, 'pagination' => ['more' => false]];
    }

    public function getTotalCollegesCount(): int
    {
        return $this->table()->countAllResults();
    }
}
