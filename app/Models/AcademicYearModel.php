<?php

namespace App\Models;

use CodeIgniter\Model;

class AcademicYearModel extends Model
{
    protected $table            = 'academic_years';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    // start_date / end_date are intentionally NOT allowed fields. The columns
    // still exist in the table and are left untouched, but nothing in the app
    // collects or writes them any more, and leaving them mass-assignable would
    // let a stray posted field reach a column no screen shows.
    protected $allowedFields    = ['year_label', 'is_current', 'created_at', 'updated_at'];
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
        return $this->table()->orderBy('year_label', 'DESC')->get()->getResultArray();
    }

    public function findByLabel(string $label): ?array
    {
        $row = $this->table()->where('year_label', $label)->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * Select2-shaped search, mirroring StreamModel::searchStreamsForSelect2().
     * `id` is the label itself, not the numeric id: every consumer
     * (Confirmations, Students new-entry, Reminders/university) filters or
     * saves against the free-text student_details.admission_taken_year
     * column, not a foreign key.
     */
    public function searchForSelect2(?string $term): array
    {
        $builder = $this->table()->select('year_label');

        if (!empty($term)) {
            $builder->like('year_label', like_term($term));
        }

        $rows = $builder->orderBy('year_label', 'DESC')->get()->getResultArray();

        $results = [];
        foreach ($rows as $r) {
            $label = $r['year_label'];
            if ($label !== '') {
                $results[] = ['id' => $label, 'text' => $label];
            }
        }

        return ['results' => $results];
    }

    /**
     * Unset is_current on every row except $exceptId.
     */
    private function clearCurrentExcept(?int $exceptId): void
    {
        $builder = $this->table()->where('is_current', 1);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        $builder->update(['is_current' => 0]);
    }

    /**
     * MySQL has no native "at most one row true" constraint, so exclusivity
     * is enforced here in a transaction: insert, then (if marked current)
     * clear every other row inside the same atomic unit.
     *
     * @throws \RuntimeException if the transaction fails
     */
    public function insertYear(array $data, bool $makeCurrent): int
    {
        $this->db->transStart();

        $data['is_current'] = $makeCurrent ? 1 : 0;
        $this->insert($data);
        $newId = $this->getInsertID();

        if ($makeCurrent) {
            $this->clearCurrentExcept($newId);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Could not save the academic year. Please try again.');
        }

        return $newId;
    }

    /**
     * @throws \RuntimeException if the transaction fails
     */
    public function updateYear(int $id, array $data, bool $makeCurrent): void
    {
        $this->db->transStart();

        $data['is_current'] = $makeCurrent ? 1 : 0;
        $this->update($id, $data);

        if ($makeCurrent) {
            $this->clearCurrentExcept($id);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Could not update the academic year. Please try again.');
        }
    }

    /**
     * @throws \RuntimeException if the transaction fails
     */
    public function markCurrent(int $id): void
    {
        $this->db->transStart();
        $this->update($id, ['is_current' => 1]);
        $this->clearCurrentExcept($id);
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            throw new \RuntimeException('Could not set the current academic year. Please try again.');
        }
    }
}
