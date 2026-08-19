<?php

namespace App\Commands;

use App\Models\AcademicYearModel;
use App\Services\AcademicYearService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Proves the academic-year delete guard against the real database.
 *
 * Read-only. Nothing here deletes anything: the guard throws before the model's
 * delete() is reached, so calling the service and catching is safe AND is the
 * only way to test the guard as the screen actually invokes it.
 *
 * A unit test with fixtures could not tell us the thing that matters here --
 * that every year in THIS database is depended upon by real records, so the
 * button was live on 22 rows that all had something behind them.
 */
class YearGuardCheck extends BaseCommand
{
    protected $group       = 'E-Section';
    protected $name        = 'esection:yearguard';
    protected $description = 'Checks that academic years in use cannot be deleted.';

    private int $failures = 0;

    private function assert(string $label, bool $ok, string $detail = ''): void
    {
        if (! $ok) {
            $this->failures++;
        }

        CLI::write(sprintf(
            '  %s %-52s %s',
            $ok ? CLI::color('PASS', 'green') : CLI::color('FAIL', 'red'),
            $label,
            $detail
        ));
    }

    public function run(array $params)
    {
        helper('esection');

        $service = new AcademicYearService();
        $model   = new AcademicYearModel();

        CLI::newLine();
        CLI::write('academic year usage', 'yellow');

        $years  = $service->getAll(true);
        $counts = $model->usageCounts();

        $this->assert('getAll(true) attaches usage_count to every row', ! array_filter(
            $years,
            static fn (array $y): bool => ! array_key_exists('usage_count', $y)
        ), count($years) . ' years');

        $this->assert('getAll() without the flag stays unchanged', ! array_filter(
            $service->getAll(),
            static fn (array $y): bool => array_key_exists('usage_count', $y)
        ), 'no caller is forced to pay for the counts');

        // The grouped read and the per-label read must agree, or the button and
        // the guard would disagree -- an enabled button that then refuses.
        $mismatch = [];
        foreach ($years as $y) {
            $direct = $model->usageCountFor($y['year_label']);

            if ($direct !== (int) $y['usage_count']) {
                $mismatch[] = $y['year_label'] . ': row=' . $y['usage_count'] . ' direct=' . $direct;
            }
        }
        $this->assert(
            'grouped counts agree with per-label counts',
            $mismatch === [],
            $mismatch === [] ? count($years) . ' compared' : implode('; ', array_slice($mismatch, 0, 3))
        );

        CLI::newLine();
        CLI::write('the guard', 'yellow');

        $used   = array_values(array_filter($years, static fn (array $y): bool => (int) $y['usage_count'] > 0));
        $unused = array_values(array_filter($years, static fn (array $y): bool => (int) $y['usage_count'] === 0));

        CLI::write('  ' . count($used) . ' year(s) in use, ' . count($unused) . ' unused');

        // Every year that is in use must be refused, naming a number.
        $wrong = [];
        foreach ($used as $y) {
            try {
                $service->delete((int) $y['id']);
                $wrong[] = $y['year_label'] . ' (NOT refused -- it was deleted!)';
            } catch (\InvalidArgumentException $e) {
                if (stripos($e->getMessage(), 'cannot be deleted') === false) {
                    $wrong[] = $y['year_label'] . ' refused for the wrong reason: ' . $e->getMessage();
                }
            }
        }
        $this->assert(
            'every in-use year is refused',
            $wrong === [],
            $wrong === [] ? count($used) . ' checked' : implode('; ', array_slice($wrong, 0, 2))
        );

        // The refusal has to say how many records, or it is not actionable.
        if ($used !== []) {
            $sample  = $used[0];
            $message = '';

            try {
                $service->delete((int) $sample['id']);
            } catch (\InvalidArgumentException $e) {
                $message = $e->getMessage();
            }

            $this->assert(
                'the refusal names the year and the count',
                str_contains($message, $sample['year_label'])
                    && str_contains($message, number_format((int) $sample['usage_count'])),
                $message
            );
        }

        // A year that does not exist must not report success.
        $missing = 0;
        while ($model->find($missing) !== null || $missing === 0) {
            $missing += 99991;
        }

        $notFound = '';
        try {
            $service->delete($missing);
        } catch (\InvalidArgumentException $e) {
            $notFound = $e->getMessage();
        }
        $this->assert(
            'a nonexistent year is refused, not reported deleted',
            stripos($notFound, 'not found') !== false,
            $notFound === '' ? 'NOTHING WAS THROWN -- it reported success' : $notFound
        );

        // Nothing above may have actually removed a row.
        $this->assert(
            'the check deleted nothing',
            count($service->getAll()) === count($years),
            count($years) . ' before, ' . count($service->getAll()) . ' after'
        );

        CLI::newLine();

        if ($this->failures > 0) {
            CLI::error($this->failures . ' check(s) failed.');

            return EXIT_ERROR;
        }

        CLI::write('All academic-year guard checks passed.', 'green');

        return EXIT_SUCCESS;
    }
}
