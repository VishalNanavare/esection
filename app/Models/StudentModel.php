<?php

namespace App\Models;

use CodeIgniter\Model;

class StudentModel extends Model
{
    protected $table            = 'student_details';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'array_space', 'to_name', 'clg_add', 'admission_taken_year',
        'student_name', 'student_nee_name', 'eligibility_case_no',
        'admission_taken_in', 'verification_of_marksheet_done_by_you',
        'in_favour_of', 'en_time'
    ];

    /**
     * The confirmation join key.
     *
     * conf_stud_data.student_id is populated by migration 000002, backfilled
     * from the legacy `name` column which holds student_details.id.
     *
     * This used to probe with fieldExists() and fall back to
     *   conf_stud_data.case_no = student_details.eligibility_case_no
     * which OVER-COUNTS: it returns 4,358 rows from a 4,208-row table because
     * 219 eligibility_case_no values are duplicated across students.
     */
    private const CONF_JOIN = 'conf_stud_data.student_id = student_details.id';

    /**
     * A fresh query builder.
     *
     * Deliberately NOT Model::builder(), which returns a single shared
     * instance -- its accumulated where/join state leaks between calls and
     * corrupts results when these methods are used inside a loop.
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    public function getMaxId(): int
    {
        $row = $this->table()->selectMax('id')->get()->getRowArray();
        return $row ? (int) $row['id'] : 0;
    }

    public function getTotalStudentsCount(): int
    {
        return $this->table()->countAllResults();
    }

    public function getStudentsByArraySpace(string $arraySpace): array
    {
        return $this->table()->where('array_space', $arraySpace)->get()->getResultArray();
    }

    public function getDistinctYears(): array
    {
        return $this->table()
                    ->select('admission_taken_year')
                    ->distinct()
                    ->where('admission_taken_year IS NOT NULL')
                    ->where('admission_taken_year !=', '')
                    ->orderBy('admission_taken_year', 'DESC')
                    ->get()->getResultArray();
    }

    public function getDistinctStreamsFromStudents(): array
    {
        return $this->table()
                    ->select('admission_taken_in')
                    ->distinct()
                    ->where('admission_taken_in IS NOT NULL')
                    ->where('admission_taken_in !=', '')
                    ->orderBy('admission_taken_in', 'ASC')
                    ->get()->getResultArray();
    }

    public function getGroupedStudentCounts(): array
    {
        $rows = $this->table()
                     ->select('admission_taken_in, COUNT(*) as cnt')
                     ->where('admission_taken_in IS NOT NULL')
                     ->where('admission_taken_in !=', '')
                     ->groupBy('admission_taken_in')
                     ->get()->getResultArray();

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['admission_taken_in']] = (int) $r['cnt'];
        }

        return $counts;
    }

    public function getGroupedConfirmedCounts(): array
    {
        $rows = $this->table()
                     ->select('student_details.admission_taken_in, COUNT(DISTINCT student_details.id) as cnt')
                     ->join('conf_stud_data', self::CONF_JOIN, 'inner')
                     ->where('student_details.admission_taken_in IS NOT NULL')
                     ->where('student_details.admission_taken_in !=', '')
                     ->groupBy('student_details.admission_taken_in')
                     ->get()->getResultArray();

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['admission_taken_in']] = (int) $r['cnt'];
        }

        return $counts;
    }

    /**
     * Students in a year/stream, left-joined to their DD confirmation.
     *
     * The dd_* columns are selected unconditionally. They previously sat
     * behind fieldExists() guards that always failed, which is why the
     * "DD Status" column in the confirmations view read "Pending" for every
     * row regardless of the underlying data.
     */
    public function getStudentsForConfirmation(string $year, string $stream): array
    {
        return $this->table()
                    ->select(
                        'student_details.*,'
                        . ' conf_stud_data.dd_no,'
                        . ' conf_stud_data.bank_name,'
                        . ' conf_stud_data.dd_date,'
                        . ' conf_stud_data.dd_amount'
                    )
                    ->join('conf_stud_data', self::CONF_JOIN, 'left')
                    ->where('student_details.admission_taken_year', $year)
                    ->like('student_details.admission_taken_in', $stream)
                    ->orderBy('student_details.id', 'ASC')
                    ->get()->getResultArray();
    }

    public function getStudentsForReminder(?string $year, ?string $stream, ?string $university): array
    {
        if (empty($year) || empty($stream)) {
            return [];
        }

        $builder = $this->table()
                        ->where('admission_taken_year', $year)
                        ->like('admission_taken_in', $stream);

        if (! empty($university)) {
            $builder->like('clg_add', $university);
        }

        return $builder->orderBy('id', 'ASC')->get()->getResultArray();
    }

    public function getStudentsByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        return $this->table()->whereIn('id', $ids)->get()->getResultArray();
    }
}
