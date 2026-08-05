<?php

namespace App\Models;

use CodeIgniter\Model;

class BackupHistoryModel extends Model
{
    protected $table            = 'backup_history';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'filename', 'type', 'file_size', 'created_by', 'created_at',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';

    /**
     * A fresh query builder. Not Model::builder() and not the Model's own
     * chainable methods -- both accumulate state on a shared instance, which
     * leaks between calls (same reasoning as StudentReminderModel/CollegeModel).
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getRecent(int $limit = 50): array
    {
        return $this->table()->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
    }

    /**
     * The ONLY lookup the download action uses -- an integer id, never a
     * client-supplied filename. See BackupService::resolveDownload().
     */
    public function findOneById(int $id): ?array
    {
        $row = $this->table()->where('id', $id)->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * Rows beyond the newest $keep of one type, oldest-first -- the retention
     * prune's delete list. Scoped per type so a burst of Excel backups can
     * never evict the SQL backups, and offset-based so the file just created
     * is structurally never a candidate.
     */
    public function getPrunable(string $type, int $keep): array
    {
        return $this->table()
                    ->where('type', $type)
                    ->orderBy('id', 'DESC')
                    // CI4 requires a limit to emit an offset; this ceiling is
                    // far above any realistic backup count.
                    ->limit(100000, $keep)
                    ->get()->getResultArray();
    }

    public function deleteById(int $id): void
    {
        $this->table()->where('id', $id)->delete();
    }
}
