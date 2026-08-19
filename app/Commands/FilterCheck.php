<?php

namespace App\Commands;

use App\Models\CollegeModel;
use App\Models\RegularizationModel;
use App\Models\StudentModel;
use App\Models\UniversityReminderBatchModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Exercises the history/search filters against the real database.
 *
 * A throwaway harness would have to hand-bootstrap the framework, which is
 * brittle; a spark command gets the same boot the application does, so what it
 * proves is what the screens actually do.
 *
 * Read-only: every call is a SELECT.
 */
class FilterCheck extends BaseCommand
{
    protected $group       = 'E-Section';
    protected $name        = 'esection:filtercheck';
    protected $description = 'Sanity-checks the history and directory filters against live data.';

    private int $failures = 0;

    public function run(array $params)
    {
        helper('esection');

        $this->students();
        $this->universities();
        $this->regularizations();
        $this->reminderBatches();
        $this->escaping();
        $this->dateRanges();
        $this->pickers();

        CLI::newLine();

        if ($this->failures > 0) {
            CLI::error($this->failures . ' check(s) failed.');

            return EXIT_ERROR;
        }

        CLI::write('All filter checks passed.', 'green');

        return EXIT_SUCCESS;
    }

    private function report(string $label, int $got, int $baseline, string $expect): void
    {
        $ok = match ($expect) {
            'narrows'  => $got < $baseline,
            'empty'    => $got === 0,
            'nonempty' => $got > 0,
            'same'     => $got === $baseline,
            default    => true,
        };

        if (! $ok) {
            $this->failures++;
        }

        CLI::write(sprintf(
            '  %s %-46s %5d of %-5d',
            $ok ? CLI::color('PASS', 'green') : CLI::color('FAIL', 'red'),
            $label,
            $got,
            $baseline
        ));
    }

    /** For checks whose answer is a yes/no rather than a row count. */
    private function assert(string $label, bool $ok, string $detail = ''): void
    {
        if (! $ok) {
            $this->failures++;
        }

        CLI::write(sprintf(
            '  %s %-46s %s',
            $ok ? CLI::color('PASS', 'green') : CLI::color('FAIL', 'red'),
            $label,
            $detail
        ));
    }

    private function students(): void
    {
        CLI::newLine();
        CLI::write('students/history', 'yellow');

        $m    = new StudentModel();
        $all  = count($m->getBatchSummaries());

        CLI::write('  baseline (no filter): ' . $all);

        $this->report('batch contains "esection"', count($m->getBatchSummaries(['batch' => 'esection'])), $all, 'nonempty');
        $this->report('batch = __nope__ (no match)', count($m->getBatchSummaries(['batch' => '__nope__'])), $all, 'empty');
        $this->report('created after 2099 (future)', count($m->getBatchSummaries(['date_from' => '2099-01-01'])), $all, 'empty');
        $this->report('created before 2000 (past)', count($m->getBatchSummaries(['date_to' => '2000-01-01'])), $all, 'empty');
        $this->report('created after 2000 (all of it)', count($m->getBatchSummaries(['date_from' => '2000-01-01'])), $all, 'same');

        // Pagination: a page must be a slice, not the whole table.
        $page = $m->getBatchSummaries([], 20);
        $this->report('paginated page is 20 rows', count($page), $all, 'narrows');

        $pager = $m->pager;
        $total = $pager === null ? 0 : $pager->getTotal();
        $this->report('pager total matches unpaginated count', $total, $all, 'same');
    }

    private function universities(): void
    {
        CLI::newLine();
        CLI::write('universities', 'yellow');

        $c   = new CollegeModel();
        $all = count($c->getAllColleges());

        CLI::write('  baseline (no filter): ' . $all);

        $states = $c->getDistinctStates();
        $first  = (string) ($states[0]['States'] ?? '');

        if ($first !== '') {
            $this->report('state = ' . $first, count($c->getAllColleges(['state' => $first])), $all, 'narrows');
        }

        $this->report('name = __nope__ (no match)', count($c->getAllColleges(['name' => '__nope__'])), $all, 'empty');
    }

    private function regularizations(): void
    {
        CLI::newLine();
        CLI::write('regularization/history', 'yellow');

        $r   = new RegularizationModel();
        $all = count($r->getAllOrdered());

        CLI::write('  baseline (no filter): ' . $all);

        $this->report('name = __nope__ (no match)', count($r->getAllOrdered(['name' => '__nope__'])), $all, 'empty');
        $this->report('created after 2099 (future)', count($r->getAllOrdered(['date_from' => '2099-01-01'])), $all, 'empty');
    }

    private function reminderBatches(): void
    {
        CLI::newLine();
        CLI::write('reminders/university/history', 'yellow');

        $b   = new UniversityReminderBatchModel();
        $all = count($b->getAllOrdered());

        CLI::write('  baseline (no filter): ' . $all);

        $this->report('university = __nope__ (no match)', count($b->getAllOrdered(['university' => '__nope__'])), $all, 'empty');
        $this->report('created after 2099 (future)', count($b->getAllOrdered(['date_from' => '2099-01-01'])), $all, 'empty');
    }

    /**
     * A literal % must be matched as a character, not as a wildcard -- that is
     * what like_term() is for, and a filter that quietly ignored it would
     * return the whole table for a meaningless search.
     */
    private function escaping(): void
    {
        CLI::newLine();
        CLI::write('LIKE escaping', 'yellow');

        $m   = new StudentModel();
        $all = count($m->getBatchSummaries());

        $this->report('name = "%" treated literally', count($m->getBatchSummaries(['name' => '%'])), $all, 'empty');
        $this->report('name = "_" treated literally', count($m->getBatchSummaries(['name' => '_'])), $all, 'empty');
    }

    /**
     * An inverted range must not silently return nothing.
     *
     * `en_time >= from AND en_time <= to` with from later than to matches no
     * row, so before ordered_date_range() the screen answered a mis-clicked
     * range with an empty table and no explanation -- and the Export button,
     * which reuses the same query string, produced an empty spreadsheet.
     */
    private function dateRanges(): void
    {
        CLI::newLine();
        CLI::write('date range ordering', 'yellow');

        $m = new StudentModel();

        $from = '2000-01-01';
        $to   = '2099-01-01';

        $right = count($m->getBatchSummaries(['date_from' => $from, 'date_to' => $to]));
        $this->report('ordered range returns rows', $right, $right, 'nonempty');

        // What the model does with the pair as given -- the bug being guarded.
        $raw = count($m->getBatchSummaries(['date_from' => $to, 'date_to' => $from]));
        $this->assert(
            'inverted range matches nothing (the bug)',
            $raw === 0,
            $raw === 0 ? 'confirmed' : 'expected 0, got ' . $raw
        );

        // And what the controllers now hand the model instead.
        [$f, $t] = ordered_date_range($to, $from);
        $fixed   = count($m->getBatchSummaries(['date_from' => $f, 'date_to' => $t]));
        $this->assert(
            'ordered_date_range() swaps it back',
            $fixed === $right,
            $fixed . ' vs ' . $right
        );

        [$f2, $t2] = ordered_date_range('2020-01-01', '');
        $this->assert('blank side left untouched', $f2 === '2020-01-01' && $t2 === '');

        [$f3, $t3] = ordered_date_range('not a date', '2020-01-01');
        $this->assert('unparseable side left untouched', $f3 === 'not a date' && $t3 === '2020-01-01');
    }

    /**
     * Every option the Batch History pickers offer must match at least one
     * batch.
     *
     * This is the check that matters. The obvious way to build these three
     * filters was to point them at the existing api/streams and
     * api/academic-years endpoints, and for courses that would have been
     * silently, totally wrong: stream_details holds "BCOM" while every batch
     * stores "F.Y.B.Com", the two sets share not one value, and the filter is
     * an exact comparison -- so all 30 options would have matched nothing.
     * That failure does not look like a bug on screen; it looks like there are
     * no records. This asserts it cannot happen.
     */
    private function pickers(): void
    {
        CLI::newLine();
        CLI::write('students/history pickers (api/batch-*)', 'yellow');

        $m = new StudentModel();

        // The university list is 418 values and each probe is a full GROUP BY
        // over the table, so that one is sampled. Stated, not silent: a capped
        // check that reads as exhaustive is worse than no check at all.
        $caps = ['year' => 0, 'course' => 0, 'university' => 40];

        foreach ($caps as $field => $cap) {
            $options = [];

            // Every page, not just the first -- an option is no less dead for
            // being on page 3.
            for ($page = 1; $page <= 100; $page++) {
                $res = $m->distinctForSelect2($field, null, $page);

                foreach ($res['results'] as $r) {
                    $options[] = $r['id'];
                }

                if (empty($res['pagination']['more'])) {
                    break;
                }
            }

            $total  = count($options);
            $probed = ($cap > 0 && $total > $cap) ? array_slice($options, 0, $cap) : $options;

            $dead = [];
            foreach ($probed as $value) {
                if (count($m->getBatchSummaries([$field => $value])) === 0) {
                    $dead[] = $value;
                }
            }

            $note = sprintf('%d of %d probed', count($probed), $total);

            if ($dead !== []) {
                $note .= ' -- DEAD: ' . implode(' | ', array_map(
                    static fn ($v): string => mb_substr((string) $v, 0, 40),
                    array_slice($dead, 0, 3)
                ));
            }

            $this->assert($field . ': every option matches a batch', $dead === [], $note);
        }

        // The placeholder text saved as data must not be offered as a course.
        $courses = $m->distinctForSelect2('course', null, 1)['results'];
        $this->assert(
            'placeholder value excluded from courses',
            ! in_array('Select Admission Taken In', array_column($courses, 'id'), true)
        );

        // An unknown field must never become an identifier in the query.
        $bogus = $m->distinctForSelect2('; DROP TABLE student_details --', null, 1);
        $this->assert('unknown field yields an empty list', $bogus['results'] === []);

        // Labels reaching a dropdown must not carry markup.
        $unis   = $m->distinctForSelect2('university', null, 1)['results'];
        $tagged = array_filter($unis, static fn (array $r): bool => stripos($r['text'], '<br') !== false);
        $this->assert('university labels are flattened', $tagged === [], count($unis) . ' checked');
    }
}
