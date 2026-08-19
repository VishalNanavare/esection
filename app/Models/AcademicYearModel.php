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
    /**
     * Every table that stores an academic year, and the column it stores it in.
     *
     * They hold the LABEL as free text, not a foreign key to academic_years.id.
     * That is why deleting a row here does not fail and does not cascade: the
     * label simply stops resolving, the batches that carry it drop out of the
     * year picker, and nothing anywhere reports a problem.
     *
     * rem_db has no model in this application -- it is a legacy table the CI4
     * rewrite reads through Reminders rather than owning -- but it holds 201
     * rows carrying year labels, so leaving it out would let a delete orphan
     * them. Any table missing from the schema is skipped at query time, so a
     * slimmer install is not an error.
     */
    private const YEAR_REFERENCES = [
        'student_details'             => 'admission_taken_year',
        'conf_stud_data'              => 'acd_year',
        'regularizations'             => 'admission_taken_year',
        'university_reminder_batches' => 'academic_year',
        'e_student_data'              => 'AdmissionYear',
        'rem_db'                      => 'acd_year',
    ];

    /**
     * How many records depend on each academic year label.
     *
     * One grouped query per referencing table rather than one per year: with 22
     * years across 6 tables the naive shape is 132 round trips to render a page
     * that used to take one.
     *
     * @return array<string,int> label => total references, only for labels used
     */
    public function usageCounts(): array
    {
        $db     = $this->db;
        $counts = [];

        foreach (self::YEAR_REFERENCES as $table => $column) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $rows = $db->table($table)
                       ->select($column . ' AS label, COUNT(*) AS n')
                       ->where($column . ' IS NOT NULL')
                       ->where($column . ' !=', '')
                       ->groupBy($column)
                       ->get()
                       ->getResultArray();

            foreach ($rows as $r) {
                $label          = (string) $r['label'];
                $counts[$label] = ($counts[$label] ?? 0) + (int) $r['n'];
            }
        }

        return $counts;
    }

    /** References to one label. Used by the delete guard, which needs only one. */
    public function usageCountFor(string $label): int
    {
        if ($label === '') {
            return 0;
        }

        $db    = $this->db;
        $total = 0;

        foreach (self::YEAR_REFERENCES as $table => $column) {
            if (! $db->tableExists($table)) {
                continue;
            }

            $total += $db->table($table)->where($column, $label)->countAllResults();
        }

        return $total;
    }

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
