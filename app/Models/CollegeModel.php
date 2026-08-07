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
        'mobile_no', 'fees', 'head_name', 'in_favour_of', 'sel_data', 'is_active'
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

    /**
     * Best-effort reverse lookup: student_details.clg_add stores the
     * university's postal Address as free text (auto-filled at entry time),
     * not a foreign key, so this is the only way back to that university's
     * own record (e.g. for its `fees`) -- an exact string match, which can
     * legitimately fail to find anything if the address text was ever
     * hand-edited after entry. Callers must treat a null return as "fees
     * unknown", not an error.
     */
    public function findByAddress(string $address): ?array
    {
        $row = $this->table()->where('Address', $address)->get()->getRowArray();

        return $row ?: null;
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
     * $activeOnly defaults true because every consumer except the
     * Universities admin page itself is "pick a target to send a new
     * letter to" -- a deactivated university should disappear from those
     * pickers. The Universities page's own filter dropdown passes false
     * (via `active_only=0`) so a deactivated row can still be found there
     * to be reactivated -- see ajax_universities_js.php.
     *
     * @return array{results: array<int, array<string, mixed>>, pagination: array{more: bool}}
     */
    public function searchColleges(string $term = '', int $page = 1, int $limit = self::PAGE_SIZE, bool $activeOnly = true): array
    {
        $page  = max(1, $page);
        $limit = min(max($limit, 1), 100);

        // Only the three columns the Select2 payload is built from. The
        // builder previously selected * -- so every dropdown query pulled the
        // full row including two varchar(1500) text columns, for 20 rows a
        // keystroke.
        $builder = $this->table()->select('id, Name, States');

        if ($activeOnly) {
            $builder->where('is_active', 1);
        }

        if ($term !== '') {
            $builder->groupStart()
                    ->like('Name', like_term($term))
                    ->orLike('States', like_term($term))
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
                // address / in_favour_of / head_name / fees / state are
                // deliberately NOT returned. Every one of the twenty
                // ajax_*_js partials was checked: the only fields any Select2
                // consumer reads from this endpoint are `id`, `text` and
                // `name`. The forms that DO need an address or payee fetch
                // them from their own endpoints -- students/new uses
                // GET students/getCollegeInfo/{id}, and the Universities Edit
                // modal uses GET universities/getJson/{id} -- both of which
                // are unchanged. Returning the payee/fee/head-of-institution
                // details of all 469 universities to every dropdown keystroke
                // was volunteering data no client asked for.
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

    /**
     * Universities as bulk-email recipients, normalised to the
     * id/name/email shape BulkEmailService works in.
     *
     * Deactivated universities are excluded -- they are deactivated precisely
     * because the office no longer corresponds with them. Rows with a blank
     * or malformed email_id are deliberately NOT filtered out here: the
     * service surfaces them in the "skipped" list so the operator can see who
     * is missing an address instead of silently mailing a shorter list.
     */
    public function getForBulkEmail(string $state = ''): array
    {
        $builder = $this->table()
                        ->select('id, Name AS name, email_id AS email, States AS state')
                        ->where('is_active', 1);

        if ($state !== '') {
            $builder->where('States', $state);
        }

        return $builder->orderBy('Name', 'ASC')->get()->getResultArray();
    }
}
