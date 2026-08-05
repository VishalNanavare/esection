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
        'in_favour_of', 'en_time', 'email'
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

    /**
     * Keeps every historical record's copied university name in sync when a
     * university is renamed in Settings -- student_details stores clg_add as
     * a plain string at entry time, not a foreign key, so without this a
     * rename would leave every past record showing the stale old name.
     * Mirrors esection_basic's config/clg_edit.php, which did the equivalent
     * UPDATE ... WHERE clg_add = <old name> as part of every university edit.
     *
     * @return int number of rows updated, for the caller to log
     */
    public function renameCollegeReferences(string $oldName, string $newName): int
    {
        if ($oldName === '' || $oldName === $newName) {
            return 0;
        }

        $this->table()->where('clg_add', $oldName)->update(['clg_add' => $newName]);

        return $this->db->affectedRows();
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

    /**
     * One row per submitted batch (array_space) -- mirrors esection_basic's
     * view.php outer loop. MAX() picks a representative value for the
     * fields that are identical across every row in the same batch
     * (clg_add/admission_taken_year/admission_taken_in/en_time).
     */
    public function getBatchSummaries(): array
    {
        return $this->table()
                    ->select(
                        'array_space, COUNT(*) AS student_count,'
                        . ' MAX(clg_add) AS clg_add, MAX(admission_taken_year) AS admission_taken_year,'
                        . ' MAX(admission_taken_in) AS admission_taken_in, MAX(en_time) AS en_time'
                    )
                    ->groupBy('array_space')
                    ->orderBy('MAX(en_time)', 'DESC')
                    ->get()->getResultArray();
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
     * Students, optionally narrowed by year/stream, left-joined to their DD
     * confirmation, paginated newest-first.
     *
     * Both filters are optional (blank = unrestricted) so this also serves
     * as the page's default "show everything" view -- Erik's own call, so
     * staff always see real data on open instead of an empty prompt to
     * search first.
     *
     * The dd_* columns are selected unconditionally. They previously sat
     * behind fieldExists() guards that always failed, which is why the
     * "DD Status" column in the confirmations view read "Pending" for every
     * row regardless of the underlying data.
     *
     * Deliberately uses the Model's own chainable builder ($this->select()
     * ->where()...) rather than the isolated table() helper every other
     * method here uses -- CI4's paginate() only operates on the Model's own
     * builder, and this is one call per request, not a loop, so the
     * "shared state leaks across iterations" risk table() exists to avoid
     * doesn't apply here.
     */
    public function getStudentsForConfirmation(string $year, string $stream, int $perPage = 25): array
    {
        // conf_stud_data.array_space must be aliased -- student_details has its
        // own, unrelated array_space column (the dispatch batch id), and
        // student_details.* is already selected above, so an unaliased second
        // array_space would silently collide under the same array key.
        $builder = $this->select(
                            'student_details.*,'
                            . ' conf_stud_data.id AS confirmation_id,'
                            . ' conf_stud_data.array_space AS confirmation_array_space'
                        )
                        ->join('conf_stud_data', self::CONF_JOIN, 'left');

        if ($year !== '') {
            $builder->where('student_details.admission_taken_year', $year);
        }
        if ($stream !== '') {
            $builder->like('student_details.admission_taken_in', $stream);
        }

        return $builder->orderBy('student_details.id', 'DESC')->paginate($perPage);
    }

    /**
     * Same filter logic as getStudentsForConfirmation() above, but every
     * matching row at once instead of one page -- feeds the Excel export,
     * which must contain exactly what's filtered on screen, not just page 1.
     */
    public function getStudentsForConfirmationAll(string $year, string $stream): array
    {
        $builder = $this->select(
                            'student_details.*,'
                            . ' conf_stud_data.id AS confirmation_id,'
                            . ' conf_stud_data.array_space AS confirmation_array_space'
                        )
                        ->join('conf_stud_data', self::CONF_JOIN, 'left');

        if ($year !== '') {
            $builder->where('student_details.admission_taken_year', $year);
        }
        if ($stream !== '') {
            $builder->like('student_details.admission_taken_in', $stream);
        }

        return $builder->orderBy('student_details.id', 'DESC')->get()->getResultArray();
    }

    /**
     * Same optional-filter, paginated, newest-first shape as
     * getStudentsForConfirmation() above, for the University Reminders
     * search page.
     */
    public function getStudentsForReminder(?string $year, ?string $stream, ?string $university, int $perPage = 25): array
    {
        $builder = $this->select('*');

        if (! empty($year)) {
            $builder->where('admission_taken_year', $year);
        }
        if (! empty($stream)) {
            $builder->like('admission_taken_in', $stream);
        }
        if (! empty($university)) {
            $builder->like('clg_add', $university);
        }

        return $builder->orderBy('id', 'DESC')->paginate($perPage);
    }

    /**
     * Same filter logic as getStudentsForReminder() above, but every
     * matching row at once instead of one page -- feeds the Excel export.
     */
    public function getStudentsForReminderAll(?string $year, ?string $stream, ?string $university): array
    {
        $builder = $this->select('*');

        if (! empty($year)) {
            $builder->where('admission_taken_year', $year);
        }
        if (! empty($stream)) {
            $builder->like('admission_taken_in', $stream);
        }
        if (! empty($university)) {
            $builder->like('clg_add', $university);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }

    public function getStudentsByIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        return $this->table()->whereIn('id', $ids)->get()->getResultArray();
    }

    /**
     * Candidates as bulk-email recipients, normalised to the id/name/email
     * shape BulkEmailService works in.
     *
     * student_details.email was only added on 2026-08-05, so the 6,345
     * pre-existing rows are all NULL until staff fill them in. Rows without
     * an address are still returned: the service lists them as "skipped" so
     * the gap is visible rather than a silently shorter send.
     */
    public function getForBulkEmail(string $year = '', string $stream = ''): array
    {
        $builder = $this->table()
                        ->select('id, student_name AS name, email, eligibility_case_no, admission_taken_in, admission_taken_year');

        if ($year !== '') {
            $builder->where('admission_taken_year', $year);
        }
        if ($stream !== '') {
            $builder->like('admission_taken_in', $stream);
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }
}
