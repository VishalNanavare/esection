<?php

namespace App\Models;

use CodeIgniter\Model;

class EmailLogModel extends Model
{
    protected $table            = 'email_log';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'batch_ref', 'template_slug', 'recipient_type', 'recipient_id',
        'recipient_name', 'recipient_email', 'subject', 'status',
        'error_message', 'attempts', 'sent_by', 'created_at',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';

    /** Fresh builder per call -- same reasoning as the other models here. */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    /**
     * Paginated send log. Uses the Model's own chainable builder because
     * paginate() only works there (same documented exception as
     * StudentModel::getStudentsForConfirmation()).
     */
    public function searchLog(string $status, string $search, int $perPage = 25): array
    {
        $builder = $this->select('*');

        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($search !== '') {
            $builder->groupStart()
                    ->like('recipient_email', like_term($search))
                    ->orLike('recipient_name', like_term($search))
                    ->groupEnd();
        }

        return $builder->orderBy('id', 'DESC')->paginate($perPage);
    }

    /** @return array{sent: int, failed: int} */
    public function statusCounts(): array
    {
        $rows = $this->table()
                     ->select('status, COUNT(*) AS total')
                     ->groupBy('status')
                     ->get()->getResultArray();

        $counts = ['sent' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /** Failed rows, for the "retry all failed" action. */
    public function getFailed(?string $batchRef = null): array
    {
        $builder = $this->table()->where('status', 'failed');

        if ($batchRef !== null && $batchRef !== '') {
            $builder->where('batch_ref', $batchRef);
        }

        return $builder->orderBy('id', 'ASC')->get()->getResultArray();
    }

    public function findOneById(int $id): ?array
    {
        $row = $this->table()->where('id', $id)->get()->getRowArray();

        return $row ?: null;
    }

    public function markResult(int $id, bool $success, string $error = ''): void
    {
        $this->table()->where('id', $id)->update([
            'status'        => $success ? 'sent' : 'failed',
            'error_message' => $success ? null : $error,
            'attempts'      => new \CodeIgniter\Database\RawSql('attempts + 1'),
        ]);
    }
}
