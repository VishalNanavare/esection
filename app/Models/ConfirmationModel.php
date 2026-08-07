<?php

namespace App\Models;

use CodeIgniter\Model;

class ConfirmationModel extends Model
{
    protected $table            = 'conf_stud_data';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    /**
     * Every field here must exist in the live table.
     *
     * The dd_* columns exist (migration 000002 added them) but are no
     * longer written by this app: they were an earlier, DD-payment-focused
     * reinterpretation of this feature that never matched what
     * esection_basic's real confirmation screen (con_view.php) actually
     * asked for -- an eligibility checklist (Migration/TC, Passing/Degree,
     * Statement of Marks, letter no/date, confirmation-from details), not
     * payment info. Confirmed: all 4208 pre-existing rows have the
     * checklist fields populated and zero have dd_no/bank_name/dd_amount.
     * Rebuilt 2026-07-30 to persist the checklist fields instead, matching
     * legacy exactly. dd_* columns are left in the schema (harmless,
     * unused) rather than dropped, in case payment tracking is wanted as a
     * separate concern later.
     *
     * The legacy columns (array_space, name, stream, uni_add, case_no,
     * acd_year) are included so rows written by this app remain readable by
     * the legacy esection_basic screens, which key off `name` and `stream`.
     */
    protected $allowedFields = [
        'student_id', 'array_space', 'name', 'stream', 'uni_add', 'case_no',
        'acd_year', 'mig_TC', 'p_degree', 's_marks', 'letter_no_date',
        'remark', 'conf_from', 'conf_from_text', 'conf_from_select',
        'etc_data', 'en_by', 'en_time',
    ];

    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getTotalConfirmedCount(): int
    {
        return $this->table()->countAllResults();
    }

    /**
     * Student ids from the given set that already have a confirmation row.
     *
     * Restores the duplicate guard the legacy app performed in
     * esection_basic/config/conf_ins.php before inserting.
     *
     * @param  int[] $studentIds
     * @return int[]
     */
    public function getConfirmedStudentIds(array $studentIds): array
    {
        $studentIds = array_values(array_filter(array_map('intval', $studentIds)));

        if ($studentIds === []) {
            return [];
        }

        $rows = $this->table()
                     ->select('student_id')
                     ->distinct()
                     ->whereIn('student_id', $studentIds)
                     ->get()->getResultArray();

        return array_map(static fn (array $r): int => (int) $r['student_id'], $rows);
    }

    /**
     * Persist one confirmation row per student, in a single transaction.
     *
     * @param  array<int, array<string, mixed>> $students   Full student_details rows
     * @param  array<int, array<string, string>> $checklist  student_id => eligibility-checklist fields
     *         (mig_tc, p_degree, s_marks, letter_no_date, remark, conf_from,
     *         conf_from_text, conf_from_select, etc_data)
     * @return int Number of rows inserted
     */
    public function storeConfirmationBatch(array $students, array $checklist, string $username): int
    {
        if ($students === []) {
            return 0;
        }

        helper('esection');

        $now        = (string) time();
        $arraySpace = $this->nextArraySpace();
        $payload    = [];

        foreach ($students as $student) {
            $studentId = (int) $student['id'];
            $fields    = $checklist[$studentId] ?? [];

            $payload[] = [
                'student_id'        => $studentId,
                'array_space'       => $arraySpace,
                // Legacy compatibility: esection_basic stores the owning
                // student's id in `name`. Keep doing so.
                'name'              => (string) $studentId,
                'stream'            => sanitize_xss((string) ($student['admission_taken_in'] ?? '')),
                'uni_add'           => sanitize_xss((string) ($student['clg_add'] ?? '')),
                'case_no'           => sanitize_xss((string) ($student['eligibility_case_no'] ?? '')),
                'acd_year'          => sanitize_xss((string) ($student['admission_taken_year'] ?? '')),
                'mig_TC'            => sanitize_xss((string) ($fields['mig_tc'] ?? 'No')),
                'p_degree'          => sanitize_xss((string) ($fields['p_degree'] ?? 'No')),
                's_marks'           => sanitize_xss((string) ($fields['s_marks'] ?? 'No')),
                'letter_no_date'    => sanitize_xss((string) ($fields['letter_no_date'] ?? '')),
                'remark'            => sanitize_xss((string) ($fields['remark'] ?? '')),
                'conf_from'         => sanitize_xss((string) ($fields['conf_from'] ?? '')),
                'conf_from_text'    => sanitize_xss((string) ($fields['conf_from_text'] ?? '')),
                'conf_from_select'  => sanitize_xss((string) ($fields['conf_from_select'] ?? '')),
                'etc_data'          => sanitize_xss((string) ($fields['etc_data'] ?? '')),
                'en_by'             => sanitize_xss($username),
                'en_time'           => $now,
            ];
        }

        $this->db->transStart();
        $this->table()->insertBatch($payload);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return 0;
        }

        return count($payload);
    }

    /**
     * One row per confirmed batch (array_space), for the history/browse
     * page -- mirrors esection_basic's conf_view.php outer loop.
     *
     * All filters optional (blank = unrestricted) -- same "show everything
     * by default" convention as StudentModel::getStudentsForConfirmation().
     * When a filter is active it narrows the underlying confirmation rows
     * BEFORE the GROUP BY, so student_count reflects only the matching
     * students in that batch, not the batch's full original size --
     * intentional, matches every other filtered listing in this app.
     *
     * student_details also has its own array_space/en_time-shaped columns
     * (a different concept -- the dispatch batch, see StudentModel), so
     * every column below is qualified with its owning table now that this
     * joins the two tables, to avoid an ambiguous-column SQL error.
     *
     * Deliberately uses the Model's own chainable builder ($this->select()
     * ->where()...), not the table() helper every other method here uses --
     * CI4's paginate() only operates on the Model's own builder, and this is
     * one call per request, not a loop. paginate()'s internal count is
     * GROUP-BY-aware (CI4 wraps the compiled query as a derived table), so
     * the reported total is the number of matching batches, not rows.
     *
     * @param string $university filters against student_details.clg_add (the
     *               current, live university name), not conf_stud_data's own
     *               uni_add copy, since a university rename only updates
     *               student_details -- filtering the stale copy would
     *               silently miss renamed records.
     */
    public function getBatchSummaries(string $acdYear, string $stream, string $university, string $studentName, int $perPage = 20): array
    {
        $builder = $this->select(
                            'conf_stud_data.array_space AS array_space,'
                            . ' COUNT(*) AS student_count,'
                            . ' MAX(conf_stud_data.en_time) AS en_time,'
                            . ' MAX(conf_stud_data.en_by) AS en_by'
                        )
                        ->join('student_details', 'student_details.id = conf_stud_data.student_id', 'left');

        if ($acdYear !== '') {
            $builder->where('conf_stud_data.acd_year', $acdYear);
        }
        if ($stream !== '') {
            $builder->like('conf_stud_data.stream', like_term($stream));
        }
        if ($university !== '') {
            $builder->like('student_details.clg_add', like_term($university));
        }
        if ($studentName !== '') {
            $builder->like('student_details.student_name', like_term($studentName));
        }

        return $builder->groupBy('conf_stud_data.array_space')
                        ->orderBy('conf_stud_data.array_space', 'DESC')
                        ->paginate($perPage);
    }

    /**
     * Same 4-filter/GROUP BY shape as getBatchSummaries() above, but every
     * matching batch at once instead of one page -- feeds the Excel export.
     */
    public function getBatchSummariesAll(string $acdYear, string $stream, string $university, string $studentName): array
    {
        $builder = $this->select(
                            'conf_stud_data.array_space AS array_space,'
                            . ' COUNT(*) AS student_count,'
                            . ' MAX(conf_stud_data.en_time) AS en_time,'
                            . ' MAX(conf_stud_data.en_by) AS en_by'
                        )
                        ->join('student_details', 'student_details.id = conf_stud_data.student_id', 'left');

        if ($acdYear !== '') {
            $builder->where('conf_stud_data.acd_year', $acdYear);
        }
        if ($stream !== '') {
            $builder->like('conf_stud_data.stream', like_term($stream));
        }
        if ($university !== '') {
            $builder->like('student_details.clg_add', like_term($university));
        }
        if ($studentName !== '') {
            $builder->like('student_details.student_name', like_term($studentName));
        }

        return $builder->groupBy('conf_stud_data.array_space')
                        ->orderBy('conf_stud_data.array_space', 'DESC')
                        ->get()->getResultArray();
    }

    /**
     * Every confirmation row in one batch, joined to student_details for
     * display fields conf_stud_data doesn't itself carry (student name,
     * nee name) -- mirrors conf_view.php's per-row student_details lookup.
     */
    public function getBatchDetail(int $arraySpace): array
    {
        return $this->table()
                    ->select(
                        'conf_stud_data.*,'
                        . ' student_details.student_name,'
                        . ' student_details.student_nee_name,'
                        . ' student_details.clg_add'
                    )
                    ->join('student_details', 'student_details.id = conf_stud_data.student_id', 'left')
                    ->where('conf_stud_data.array_space', $arraySpace)
                    ->orderBy('conf_stud_data.id', 'ASC')
                    ->get()->getResultArray();
    }

    /**
     * Mirrors esection_basic's config/stud-delete.php `?r=` branch: removes
     * one confirmed student's record from history, not the whole batch.
     * Use the inherited find($id) to look up a row before deleting.
     */
    public function deleteById(int $id): bool
    {
        $this->table()->where('id', $id)->delete();

        return $this->db->affectedRows() > 0;
    }

    /**
     * Next batch grouping id, mirroring how the legacy app groups a dispatch.
     */
    private function nextArraySpace(): int
    {
        $row = $this->table()->selectMax('array_space')->get()->getRowArray();

        return (int) ($row['array_space'] ?? 0) + 1;
    }
}
