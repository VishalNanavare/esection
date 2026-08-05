<?php

namespace App\Models;

use CodeIgniter\Model;

class UserPageAccessModel extends Model
{
    protected $table            = 'user_page_access';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['user_id', 'page_key', 'granted_by', 'granted_at'];
    protected $useTimestamps    = false;

    /**
     * A fresh query builder. Not Model::builder() and not the Model's own
     * chainable methods -- both accumulate state on a shared instance, which
     * leaks between calls (same reasoning as CollegeModel/AcademicYearModel).
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getPageKeysForUser(int $userId): array
    {
        $rows = $this->table()->select('page_key')->where('user_id', $userId)->get()->getResultArray();

        return array_column($rows, 'page_key');
    }

    /**
     * One query, grouped: [user_id => [page_key, page_key, ...], ...].
     * Feeds the Access Rights matrix without an N+1 per row.
     */
    public function getGrantsGroupedByUser(): array
    {
        $rows = $this->table()->select('user_id, page_key')->get()->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['user_id']][] = $row['page_key'];
        }

        return $grouped;
    }

    /**
     * Replaces exactly this user's grants with $pageKeys -- diffs current vs
     * requested so unchanged pages are left alone, wrapped in a transaction
     * so a partial failure never leaves a half-updated grant set.
     *
     * @throws \RuntimeException if the transaction fails
     */
    public function replaceGrantsForUser(int $userId, array $pageKeys, ?int $grantedBy): void
    {
        $current = $this->getPageKeysForUser($userId);
        $toAdd    = array_values(array_diff($pageKeys, $current));
        $toRemove = array_values(array_diff($current, $pageKeys));

        if ($toAdd === [] && $toRemove === []) {
            return;
        }

        $this->db->transStart();

        if ($toRemove !== []) {
            $this->table()->where('user_id', $userId)->whereIn('page_key', $toRemove)->delete();
        }

        if ($toAdd !== []) {
            $now = date('Y-m-d H:i:s');
            $insertData = array_map(static fn (string $pageKey) => [
                'user_id'    => $userId,
                'page_key'   => $pageKey,
                'granted_by' => $grantedBy,
                'granted_at' => $now,
            ], $toAdd);

            $this->table()->insertBatch($insertData);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Could not save page access for this user. Please try again.');
        }
    }

    /**
     * Grants every page in $allPageKeys, skipping any already granted.
     * Used only when a user is known to have zero rows (new user, or the
     * "ensure default" hook) -- never call this to overwrite a curated set.
     */
    public function grantAllPages(int $userId, array $allPageKeys, ?int $grantedBy): void
    {
        if ($allPageKeys === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $insertData = array_map(static fn (string $pageKey) => [
            'user_id'    => $userId,
            'page_key'   => $pageKey,
            'granted_by' => $grantedBy,
            'granted_at' => $now,
        ], $allPageKeys);

        $this->table()->insertBatch($insertData);
    }
}
