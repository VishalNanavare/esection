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
     * The previous list included `stud_id`, which exists nowhere, and the
     * dd_* / student_id / en_by columns which did not exist until migration
     * 000002 added them. Because CI4 filters insert payloads through this
     * list and then issues the INSERT verbatim, every DD confirmation submit
     * threw "Unknown column ... in 'field list'" -- the feature had never
     * persisted a single row.
     *
     * The legacy columns (array_space, name, stream, uni_add, case_no,
     * acd_year) are included so rows written by this app remain readable by
     * the legacy esection_basic screens, which key off `name` and `stream`.
     */
    protected $allowedFields = [
        'student_id', 'array_space', 'name', 'stream', 'uni_add', 'case_no',
        'acd_year', 'dd_no', 'bank_name', 'dd_date', 'dd_amount',
        'remark', 'en_by', 'en_time',
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
     * @param  array<int, array<string, mixed>> $students Full student_details rows
     * @return int Number of rows inserted
     */
    public function storeConfirmationBatch(array $students, array $ddDetails, string $username): int
    {
        if ($students === []) {
            return 0;
        }

        helper('esection');

        $now       = (string) time();
        $arraySpace = $this->nextArraySpace();
        $payload   = [];

        foreach ($students as $student) {
            $payload[] = [
                'student_id'  => (int) $student['id'],
                'array_space' => $arraySpace,
                // Legacy compatibility: esection_basic stores the owning
                // student's id in `name`. Keep doing so.
                'name'        => (string) $student['id'],
                'stream'      => sanitize_xss((string) ($student['admission_taken_in'] ?? '')),
                'uni_add'     => sanitize_xss((string) ($student['clg_add'] ?? '')),
                'case_no'     => sanitize_xss((string) ($student['eligibility_case_no'] ?? '')),
                'acd_year'    => sanitize_xss((string) ($student['admission_taken_year'] ?? '')),
                'dd_no'       => sanitize_xss((string) ($ddDetails['dd_no'] ?? '')),
                'bank_name'   => sanitize_xss((string) ($ddDetails['bank_name'] ?? '')),
                'dd_date'     => sanitize_xss((string) ($ddDetails['dd_date'] ?? '')),
                'dd_amount'   => sanitize_xss((string) ($ddDetails['dd_amount'] ?? '')),
                'remark'      => sanitize_xss((string) ($ddDetails['remark'] ?? '')),
                'en_by'       => sanitize_xss($username),
                'en_time'     => $now,
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
     * Next batch grouping id, mirroring how the legacy app groups a dispatch.
     */
    private function nextArraySpace(): int
    {
        $row = $this->table()->selectMax('array_space')->get()->getRowArray();

        return (int) ($row['array_space'] ?? 0) + 1;
    }
}
