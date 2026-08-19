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
}
