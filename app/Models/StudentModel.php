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

    /**
     * Is this eligibility case number already taken?
     *
     * Used to keep the suggested case number on the New Entry form clear of
     * numbers already in use. There is no unique index on the column (and
     * one cannot simply be added -- 218 duplicated values already exist from
     * legacy imports), so uniqueness of a SUGGESTION has to be checked here.
     */
    public function caseNoExists(string $caseNo): bool
    {
        if ($caseNo === '') {
            return false;
        }

        return $this->table()->where('eligibility_case_no', $caseNo)->countAllResults() > 0;
    }

    /**
     * Which of these case numbers already exist, as a value => true lookup.
     *
     * The importer needs the same answer as caseNoExists() but for a whole
     * file at once. Calling that per row would be one query per candidate --
     * up to 200 round trips just to build a preview.
     *
     * @param  string[] $caseNos
     * @return array<string, bool>
     */
    public function findExistingCaseNos(array $caseNos): array
    {
        $caseNos = array_values(array_unique(array_filter(
            array_map(static fn ($v): string => trim((string) $v), $caseNos),
            static fn (string $v): bool => $v !== ''
        )));

        if ($caseNos === []) {
            return [];
        }

        $rows = $this->table()
                     ->select('eligibility_case_no')
                     ->whereIn('eligibility_case_no', $caseNos)
                     ->get()->getResultArray();

        $found = [];
        foreach ($rows as $row) {
            $found[(string) $row['eligibility_case_no']] = true;
        }

        return $found;
    }

    /**
     * Is this batch grouping key already in use?
     *
     * array_space has no uniqueness constraint, and two batches sharing one
     * silently merge on every batch screen and dispatch letter. Checked before
     * a batch is written so a collision steps to the next number instead.
     */
    public function arraySpaceExists(string $arraySpace): bool
    {
        if ($arraySpace === '') {
            return false;
        }

        return $this->table()->where('array_space', $arraySpace)->countAllResults() > 0;
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
    /**
     * Dispatch batches, newest first, with every filter optional.
     *
     * A blank filter means "no restriction", so the screen still opens showing
     * real data rather than an empty prompt to search first -- the same rule
     * the confirmations screens already follow.
     *
     * The candidate name and the batch reference are matched per ROW and so
     * belong in WHERE; the rest describe the batch and are matched against the
     * grouped MAX(), which has to be HAVING because the group does not exist
     * yet when WHERE runs.
     *
     * en_time is a unix timestamp stored as text, so the date filter converts
     * the chosen day to its start/end seconds rather than comparing strings.
     *
     * @param array<string,string> $filters  year, university, batch, name, date_from, date_to
     * @param int|null              $perPage  null returns every match (the export);
     *                                        a number paginates via the model builder
     */
    public function getBatchSummaries(array $filters = [], ?int $perPage = null): array
    {
        // $this->select(...) -- the MODEL's own builder -- rather than the
        // isolated table() helper the rest of this class uses. CI4's paginate()
        // only ever operates on the model builder, so a paginated query has to
        // start there. Safe here because this is one call per request, not a
        // loop, so the shared-state leak table() exists to avoid cannot bite.
        $builder = ($perPage === null ? $this->table() : $this)
                        ->select(
                            'array_space, COUNT(*) AS student_count,'
                            . ' MAX(clg_add) AS clg_add, MAX(admission_taken_year) AS admission_taken_year,'
                            . ' MAX(admission_taken_in) AS admission_taken_in, MAX(en_time) AS en_time'
                        );

        // --- per-row, so WHERE ---
        if (($filters['name'] ?? '') !== '') {
            $builder->like('student_name', like_term($filters['name']));
        }

        if (($filters['batch'] ?? '') !== '') {
            $builder->like('array_space', like_term($filters['batch']));
        }

        if (($filters['date_from'] ?? '') !== '') {
            $from = strtotime($filters['date_from'] . ' 00:00:00');

            if ($from !== false) {
                $builder->where('en_time >=', $from);
            }
        }

        if (($filters['date_to'] ?? '') !== '') {
            $to = strtotime($filters['date_to'] . ' 23:59:59');

            if ($to !== false) {
                $builder->where('en_time <=', $to);
            }
        }

        $builder->groupBy('array_space');

        // --- per-group, so HAVING ---
        if (($filters['year'] ?? '') !== '') {
            $builder->having('MAX(admission_taken_year)', $filters['year']);
        }

        if (($filters['university'] ?? '') !== '') {
            $builder->having('MAX(clg_add) LIKE', '%' . like_term($filters['university']) . '%');
        }

        if (($filters['course'] ?? '') !== '') {
            $builder->having('MAX(admission_taken_in)', $filters['course']);
        }

        $builder->orderBy('MAX(en_time)', 'DESC');

        // Unpaginated when $perPage is null -- the Excel export needs every
        // matching batch in one go, not a page of them.
        return $perPage === null
            ? $builder->get()->getResultArray()
            : $builder->paginate($perPage);
    }

    /**
     * The batch-history columns a Select2 filter may list values from.
     *
     * Keys are what the request asks for, values are the real column names --
     * the request never names a column itself.
     */
    private const FILTER_COLUMNS = [
        'year'       => 'admission_taken_year',
        'university' => 'clg_add',
        'course'     => 'admission_taken_in',
    ];

    /** Placeholder text saved as if it were data. Never offered as a filter. */
    private const FILTER_NOISE = [
        'Select Admission Taken In',
    ];

    /**
     * The values a batch-history filter can actually match, shaped for Select2.
     *
     * Deliberately read from student_details rather than from the master
     * tables the other pickers use, because for two of these three columns the
     * master list is simply not what is stored:
     *
     *   - admission_taken_in holds "F.Y.B.Com", "MCOM Part I", "MA Part I".
     *     stream_details -- which is what api/streams serves -- holds "BCOM",
     *     "MCOM", "MA". Zero of the 21 course values in use appear in that
     *     table, and zero of its 30 rows are used by any batch. Since
     *     getBatchSummaries() matches course with HAVING MAX(...) = ?, an exact
     *     comparison, a picker fed from stream_details would return no rows for
     *     every single option it offered.
     *   - admission_taken_year does currently line up with academic_years, but
     *     offering a year nobody has a batch in is still a filter that can only
     *     return nothing.
     *
     * A list built from the data cannot develop either fault: every option it
     * offers is, by construction, an option that matches at least one batch.
     *
     * @param  string      $field one of the keys of self::FILTER_COLUMNS
     * @return array{results: array<int, array{id: string, text: string}>, pagination: array{more: bool}}
     */
    public function distinctForSelect2(string $field, ?string $term, int $page = 1, int $perPage = 30): array
    {
        // Whitelist, not interpolation. A column name reaches the query as an
        // identifier, which the binder does not protect -- so the request never
        // chooses one, it only chooses between these.
        $column = self::FILTER_COLUMNS[$field] ?? null;

        if ($column === null) {
            return ['results' => [], 'pagination' => ['more' => false]];
        }

        $builder = $this->table()
                        ->select($column . ' AS v')
                        ->where($column . ' IS NOT NULL')
                        ->where($column . ' !=', '')
                        ->groupBy($column);

        // Six rows carry the literal placeholder text of an un-chosen dropdown
        // in admission_taken_in. It is a data-entry artefact, not a course, and
        // offering it as something to filter by would be offering a typo.
        // Excluded by exact value: anything looser risks hiding a real course
        // that happens to begin with the same word.
        foreach (self::FILTER_NOISE as $noise) {
            $builder->where($column . ' !=', $noise);
        }

        if ($term !== null && $term !== '') {
            $builder->like($column, like_term($term));
        }

        // Newest year first -- that is the one being worked on. The other two
        // are names, where alphabetical is what lets someone find one.
        $builder->orderBy($column, $field === 'year' ? 'DESC' : 'ASC');

        $page   = max(1, $page);
        $offset = ($page - 1) * $perPage;

        // One extra row, purely to answer "is there another page?" without a
        // second COUNT query. It is dropped before the reply is built.
        $rows = $builder->limit($perPage + 1, $offset)->get()->getResultArray();

        $more = count($rows) > $perPage;
        if ($more) {
            array_pop($rows);
        }

        $results = [];

        foreach ($rows as $r) {
            $value = (string) $r['v'];

            $results[] = [
                // The id is the RAW stored value: it is what the filter
                // compares against, so cleaning it here would stop it matching.
                'id' => $value,
                // 154 of the 418 stored addresses contain a literal "<br>".
                // Select2 renders its option text as text, so an uncleaned
                // label shows the tag itself to the operator.
                'text' => $field === 'university' ? flatten_address($value) : $value,
            ];
        }

        return [
            'results'    => $results,
            'pagination' => ['more' => $more],
        ];
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
            $builder->like('student_details.admission_taken_in', like_term($stream));
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
            $builder->like('student_details.admission_taken_in', like_term($stream));
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
            $builder->like('admission_taken_in', like_term($stream));
        }
        if (! empty($university)) {
            $builder->like('clg_add', like_term($university));
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
            $builder->like('admission_taken_in', like_term($stream));
        }
        if (! empty($university)) {
            $builder->like('clg_add', like_term($university));
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
            $builder->like('admission_taken_in', like_term($stream));
        }

        return $builder->orderBy('id', 'DESC')->get()->getResultArray();
    }
}
