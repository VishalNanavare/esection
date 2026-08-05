<?php

namespace App\Commands;

use App\Services\BackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Runs a backup from the command line, so Windows Task Scheduler (or any
 * cron-equivalent) can produce unattended backups.
 *
 * Calls exactly the same BackupService methods as the Settings screen, so
 * there is one backup code path rather than a second, drifting one.
 *
 *   php spark backup:run --type sql
 *   php spark backup:run --type excel
 *   php spark backup:run --type both
 */
class BackupRun extends BaseCommand
{
    protected $group       = 'Backup';
    protected $name        = 'backup:run';
    protected $description = 'Creates a SQL and/or Excel backup, as the Backup settings screen does.';
    protected $usage       = 'backup:run [--type sql|excel|both]';
    protected $options     = ['--type' => 'Which backup to create: sql, excel or both. Default: both.'];

    public function run(array $params)
    {
        $type = strtolower((string) ($params['type'] ?? CLI::getOption('type') ?? 'both'));

        if (! in_array($type, ['sql', 'excel', 'both'], true)) {
            CLI::error('--type must be one of: sql, excel, both');

            return EXIT_ERROR;
        }

        $service = new BackupService();
        $failed  = false;

        // No session in CLI, so attribute these to the scheduler rather than
        // letting created_by fall back to a null username.
        $createdBy = 'Scheduled';

        if ($type === 'sql' || $type === 'both') {
            $failed = ! $this->runOne('SQL', static fn (): array => $service->createSqlBackup($createdBy)) || $failed;
        }

        if ($type === 'excel' || $type === 'both') {
            $failed = ! $this->runOne('Excel', static fn (): array => $service->createExcelBackup($createdBy)) || $failed;
        }

        return $failed ? EXIT_ERROR : EXIT_SUCCESS;
    }

    private function runOne(string $label, callable $work): bool
    {
        try {
            $result = $work();
            CLI::write($label . ' backup created: ' . $result['filename']
                . ' (' . number_format($result['size'] / 1024, 1) . ' KB)', 'green');

            return true;
        } catch (\Throwable $e) {
            CLI::error($label . ' backup failed: ' . $e->getMessage());
            log_message('error', '[backup:run] {label} failed: {message}', ['label' => $label, 'message' => (string) $e]);

            return false;
        }
    }
}
