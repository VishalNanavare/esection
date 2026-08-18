<?php

namespace App\Services;

use App\Models\BackupHistoryModel;

/**
 * Backup engine -- two products from one screen:
 *
 *  - SQL: a complete mysqldump of every table, wrapped in an AES-256
 *    password-protected ZIP. This is the rescue copy; without the password
 *    the archive cannot be opened.
 *  - Excel: reference/configuration data only, one sheet per table, as a
 *    plain (unprotected) workbook for people to read. Student records are
 *    deliberately excluded -- they live in the SQL copy.
 *
 * Only successful backups are recorded. Every failure path deletes its
 * partial output and writes no history row, so a row in backup_history
 * always means "this file exists and passed its integrity checks".
 */
class BackupService
{
    public const TYPE_SQL   = 'sql';
    public const TYPE_EXCEL = 'excel';

    /**
     * Absolute path to the mysqldump binary. Deployment-specific, so it is
     * read from .env (`backup.mysqldumpPath`) instead of being committed: a
     * hardcoded path both pins the app to one machine and publishes the
     * server's filesystem layout to anyone who can read the repository.
     */
    private function mysqldumpPath(): string
    {
        return (string) (env('backup.mysqldumpPath') ?: 'mysqldump');
    }

    /**
     * Tables whose data goes into the Excel workbook, mapped to the columns
     * to EXCLUDE. An allowlist, not a denylist, on purpose: a future
     * migration adding a table with sensitive columns would silently land in
     * a plaintext workbook under a denylist. Under an allowlist it is simply
     * omitted -- and since the SQL backup contains 100% of every table, an
     * omission costs nothing while a leak is unrecoverable.
     *
     * Student-record tables are absent by design (the client's "excel file
     * export only data not about students"): student_details, conf_stud_data,
     * e_student_data, reg_data, rem_db, student_rem, regularizations,
     * student_reminders, university_reminder_batches,
     * university_reminder_notes.
     */
    private const EXCEL_TABLES = [
        'college_details'  => [],
        'courses'          => [],
        'academic_years'   => [],
        'stream_details'   => [],
        'access_pages'     => [],
        'user_page_access' => [],
        // Bcrypt hashes for every account must never reach a workbook that
        // ships WITHOUT password protection and gets emailed around.
        'users'            => ['password_hash'],
        'settings'         => [],
        'activity_log'     => [],
        'migrations'       => [],
    ];

    /**
     * Row-level exclusions -- a column filter cannot express these.
     *
     * Every secret the application stores lands in the same `settings`
     * table as ordinary configuration, so the Excel workbook -- which
     * ships WITHOUT password protection, by design, for people to read --
     * has to drop those rows by key. Shipping even the ciphertext is
     * needless exposure: it travels by email and onto laptops, and it is
     * the encryption key in .env, not the ciphertext's obscurity, that is
     * doing the protecting.
     *
     * Keys are referenced from the owning service's constant wherever one
     * exists, so renaming a setting cannot silently reopen the leak.
     * The SQL backup is unaffected -- it is complete and AES-256 encrypted.
     *
     * @var array<string, array<string, string[]>>
     */
    private const EXCEL_ROW_FILTERS = [
        'settings' => ['setting_key' => [
            'backup_zip_password',
            MailSettingsService::KEY_PASSWORD,
        ]],
    ];

    protected BackupHistoryModel $backupHistoryModel;
    protected BackupPasswordService $backupPasswordService;
    protected ExcelExportService $excelExportService;
    protected ActivityLogService $activityLogService;
    protected $db;

    public function __construct()
    {
        $this->backupHistoryModel    = new BackupHistoryModel();
        $this->backupPasswordService = new BackupPasswordService();
        $this->excelExportService    = new ExcelExportService();
        $this->activityLogService    = new ActivityLogService();
        $this->db                    = \Config\Database::connect();
    }

    public function backupDirectory(): string
    {
        return WRITEPATH . 'backups' . DIRECTORY_SEPARATOR;
    }

    /**
     * Complete database dump -> AES-256 password-protected ZIP.
     *
     * @return array{id: int, filename: string, size: int}
     * @throws \RuntimeException on any preflight or integrity failure
     */
    public function createSqlBackup(?string $createdBy = null): array
    {
        // The password is resolved FIRST, before anything is written. If none
        // is configured (or it cannot be decrypted) this throws here, which
        // makes producing an unencrypted .sql structurally impossible rather
        // than merely avoided.
        $password = $this->backupPasswordService->reveal();

        $this->ensureBackupDirectory();

        $mysqldump = $this->mysqldumpPath();

        if (! is_file($mysqldump)) {
            // The resolved path goes to the log, not to the response: telling a
            // browser where the binary was expected hands out the server's
            // directory layout for free.
            log_message('error', '[BackupService] mysqldump not found at ' . $mysqldump);

            throw new \RuntimeException('Database backup tool not found. Ask your system administrator to check the backup.mysqldumpPath setting.');
        }

        return $this->withLock(function () use ($password, $createdBy): array {
            $dir      = $this->backupDirectory();
            $base     = date('Y-m-d_His') . '_esection_full_backup';
            $sqlTmp   = $dir . $base . '.sql.tmp';
            $zipFinal = $dir . $base . '.zip';

            try {
                $this->runMysqldump($sqlTmp);
                $this->assertDumpIsComplete($sqlTmp);
                $this->packageEncryptedZip($sqlTmp, $base . '.sql', $zipFinal, $password);
            } catch (\Throwable $e) {
                if (is_file($zipFinal)) {
                    @unlink($zipFinal);
                }

                throw $e;
            } finally {
                // The plaintext dump is removed on EVERY path.
                if (is_file($sqlTmp)) {
                    @unlink($sqlTmp);
                }
            }

            return $this->recordSuccess($base . '.zip', self::TYPE_SQL, $zipFinal, $createdBy);
        });
    }

    /**
     * Reference/configuration data -> plain multi-sheet workbook.
     *
     * @return array{id: int, filename: string, size: int, sheets: int}
     */
    public function createExcelBackup(?string $createdBy = null): array
    {
        $this->ensureBackupDirectory();

        return $this->withLock(function () use ($createdBy): array {
            $dir      = $this->backupDirectory();
            $filename = date('Y-m-d_His') . '_esection_reference_data.xlsx';
            $path     = $dir . $filename;

            $sheets = [];
            foreach (self::EXCEL_TABLES as $table => $excludedColumns) {
                if (! $this->db->tableExists($table)) {
                    continue;
                }

                $columns = $this->allowedColumns($table, $excludedColumns);
                if ($columns === []) {
                    continue;
                }

                $sheets[] = [
                    'name'    => $table,
                    'columns' => array_map(static fn (string $c): array => ['header' => $c], $columns),
                    'rows'    => $this->rowGenerator($table, $columns),
                    'summary' => 'Table: ' . $table . ' | ' . $this->countRows($table) . ' row(s) | Backed up ' . date('d/m/Y H:i'),
                ];
            }

            if ($sheets === []) {
                throw new \RuntimeException('No tables were available to back up.');
            }

            // Throws (and cleans up its own partial file) on failure.
            $this->excelExportService->writeWorkbookToFile($sheets, $path);

            if (! is_file($path) || filesize($path) === 0) {
                throw new \RuntimeException('The Excel backup file could not be written.');
            }

            $result          = $this->recordSuccess($filename, self::TYPE_EXCEL, $path, $createdBy);
            $result['sheets'] = count($sheets);

            return $result;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $limit = 50): array
    {
        return $this->backupHistoryModel->getRecent($limit);
    }

    /**
     * Resolve a history id to a real file on disk.
     *
     * Path traversal is defended in layers: the route accepts only (:num), so
     * no client-supplied filename ever reaches the filesystem; the name comes
     * from our own database row; basename() strips any directory component;
     * the path is rebuilt from a trusted constant; and realpath() containment
     * catches anything remaining, including a symlink planted in the backup
     * directory.
     *
     * @return array{path: string, filename: string}
     * @throws \RuntimeException when the row or the file is gone
     */
    public function resolveDownload(int $id): array
    {
        $row = $this->backupHistoryModel->findOneById($id);
        if ($row === null) {
            throw new \RuntimeException('That backup no longer exists.');
        }

        $filename = basename((string) $row['filename']);
        $real     = realpath($this->backupDirectory() . $filename);
        $realDir  = realpath($this->backupDirectory());

        if ($real === false || $realDir === false
            || ! str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('That backup file is missing from the server. It may have been deleted or moved.');
        }

        return ['path' => $real, 'filename' => $filename];
    }

    /**
     * Delete one backup: the file, then its history row.
     *
     * Route takes (:num) and the row is looked up by id, so a filename never
     * comes from the request -- same shape as resolveDownload(). basename()
     * and the realpath containment check below are belt-and-braces on top of
     * that: they also defeat a value that reached the column some other way,
     * and a symlink planted in the backup folder.
     *
     * The history row is removed even when the file is already gone, so the
     * list self-heals instead of accumulating entries pointing at nothing --
     * the same rule pruneOldBackups() follows.
     *
     * Irreversible by nature, so it is admin-only (the whole Backup screen is
     * behind adminFilter), confirmed in the UI, and audit-logged below: a
     * deleted backup is a destroyed recovery point, and "who removed it" is
     * exactly the question that gets asked afterwards.
     *
     * @return string the filename that was deleted, for the flash message
     * @throws \RuntimeException when the id is unknown
     */
    public function deleteBackup(int $id): string
    {
        $row = $this->backupHistoryModel->findOneById($id);

        if ($row === null) {
            throw new \RuntimeException('That backup no longer exists.');
        }

        $filename = basename((string) $row['filename']);
        $real     = realpath($this->backupDirectory() . $filename);
        $realDir  = realpath($this->backupDirectory());

        if ($real !== false && $realDir !== false
            && str_starts_with($real, $realDir . DIRECTORY_SEPARATOR)
            && is_file($real)) {
            @unlink($real);
        }

        $this->backupHistoryModel->deleteById($id);

        $this->activityLogService->record(
            'backup.delete',
            'backup',
            $id,
            'Deleted backup ' . $filename . ' (' . strtoupper((string) $row['type']) . ')'
        );

        return $filename;
    }

    /**
     * Keep the newest N backups of one type; delete the rest.
     *
     * Count-based, not age-based: an age rule ("delete older than 30 days")
     * can leave zero backups if nobody runs one for a month, whereas a count
     * rule always keeps the last N however old they are.
     *
     * Called only AFTER a successful backup of that type, so a failing dump
     * can never quietly erase the last good ones.
     */
    public function pruneOldBackups(string $type): int
    {
        $keep    = $this->backupPasswordService->getRetentionCount();
        $deleted = 0;

        foreach ($this->backupHistoryModel->getPrunable($type, $keep) as $row) {
            $path = $this->backupDirectory() . basename((string) $row['filename']);
            if (is_file($path)) {
                @unlink($path);
            }
            // Delete the row even when the file was already gone, so the
            // history self-heals rather than accumulating dead entries.
            $this->backupHistoryModel->deleteById((int) $row['id']);
            $deleted++;
        }

        $this->sweepStaleTempFiles();

        return $deleted;
    }

    // ---------------------------------------------------------------- internals

    private function ensureBackupDirectory(): void
    {
        $dir = $this->backupDirectory();

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('The backup folder could not be created at ' . $dir . '. Check the folder permissions.');
        }

        if (! is_writable($dir)) {
            throw new \RuntimeException('The backup folder is not writable: ' . $dir);
        }
    }

    /**
     * One backup at a time. LOCK_NB so a second attempt fails immediately
     * with a clear message instead of stacking blocked PHP workers; the OS
     * releases the lock if the process dies, so a crash cannot wedge the
     * feature permanently.
     */
    private function withLock(callable $work)
    {
        $handle = fopen($this->backupDirectory() . '.backup.lock', 'c');

        if ($handle === false) {
            throw new \RuntimeException('Could not acquire the backup lock file.');
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            throw new \RuntimeException('A backup is already running. Please wait for it to finish and try again.');
        }

        try {
            return $work();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function runMysqldump(string $outputPath): void
    {
        $db = config('Database')->default;

        $command = [
            $this->mysqldumpPath(),
            '-h', (string) $db['hostname'],
            '-P', (string) ($db['port'] ?? 3306),
            '-u', (string) $db['username'],
            // Positional database name, deliberately NOT --databases.
            // --databases embeds "CREATE DATABASE" + "USE <dbname>;" in
            // the dump, which (a) forces the original database name on
            // restore and (b) makes `mysql some_other_db < dump.sql` silently
            // write into the LIVE database instead of the one named on the
            // command line. A rescue file must let the operator choose the
            // target: create an empty database, then restore into it.
            (string) $db['database'],
            // All tables are InnoDB, so this gives a consistent snapshot with
            // no locking. Revisit if a MyISAM table is ever introduced.
            '--single-transaction',
            '--quick',
            '--routines', '--triggers', '--events',
            // This server has GTIDs enabled; without this the dump embeds
            // SET @@GLOBAL.GTID_PURGED and FAILS on restore.
            '--set-gtid-purged=OFF',
            '--default-character-set=utf8mb4',
            '--hex-blob',
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            // Straight to disk: PHP never buffers the dump, so size does not
            // matter.
            1 => ['file', $outputPath, 'w'],
            2 => ['pipe', 'w'],
        ];

        // MYSQL_PWD, never -p: with a blank password "-p" makes mysqldump
        // prompt interactively and hang the worker forever. Credentials come
        // from CI4's own DB config, so this keeps working unchanged once a
        // real password is set.
        $env = array_merge(getenv(), ['MYSQL_PWD' => (string) ($db['password'] ?? '')]);

        $process = proc_open($command, $descriptors, $pipes, null, $env);

        if (! is_resource($process)) {
            throw new \RuntimeException('The database backup process could not be started.');
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            log_message('error', '[BackupService] mysqldump exit {code}: {err}', ['code' => $exitCode, 'err' => $stderr]);

            throw new \RuntimeException('The database backup failed (code ' . $exitCode . '). The issue has been logged.');
        }

        // NOTE: a non-empty stderr is NOT a failure signal. This server emits
        // GTID/warning noise on stderr with exit code 0; only the exit code
        // and the integrity checks below are authoritative.
        if ($stderr !== '') {
            log_message('info', '[BackupService] mysqldump notices: {err}', ['err' => $stderr]);
        }
    }

    /**
     * mysqldump writes "-- Dump completed on ..." as its final line. Checking
     * for it catches a dump truncated mid-stream (disk full, killed process),
     * which leaves a plausible-looking but unusable file that the exit code
     * alone does not always flag.
     */
    private function assertDumpIsComplete(string $path): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new \RuntimeException('The database backup produced an empty file.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('The database backup file could not be read back for verification.');
        }

        fseek($handle, max(0, filesize($path) - 200), SEEK_SET);
        $tail = (string) fread($handle, 200);
        fclose($handle);

        if (! str_contains($tail, '-- Dump completed')) {
            throw new \RuntimeException('The database backup looks incomplete and was discarded. Check the available disk space and try again.');
        }
    }

    private function packageEncryptedZip(string $sourcePath, string $entryName, string $zipPath, #[\SensitiveParameter] string $password): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('The backup archive could not be created.');
        }

        $zip->setPassword($password);

        if (! $zip->addFile($sourcePath, $entryName)) {
            $zip->close();

            throw new \RuntimeException('The database dump could not be added to the backup archive.');
        }

        // Must come after addFile(). Both this and close() return a bool that
        // has to be checked -- ZipArchive defers all real work to close(), so
        // ignoring it is how an unencrypted or zero-byte archive gets through.
        if (! $zip->setEncryptionName($entryName, \ZipArchive::EM_AES_256)) {
            $zip->close();

            throw new \RuntimeException('The backup archive could not be password-protected, so it was discarded.');
        }

        if (! $zip->close()) {
            throw new \RuntimeException('The backup archive could not be finalised.');
        }

        if (! is_file($zipPath) || filesize($zipPath) === 0) {
            throw new \RuntimeException('The backup archive was written empty.');
        }
    }

    /**
     * @return array{id: int, filename: string, size: int}
     */
    private function recordSuccess(string $filename, string $type, string $path, ?string $createdBy): array
    {
        $size = (int) filesize($path);

        $id = (int) $this->backupHistoryModel->insert([
            'filename'   => $filename,
            'type'       => $type,
            'file_size'  => $size,
            'created_by' => $createdBy ?? (session()->get('username') ?? 'System'),
        ], true);

        $this->pruneOldBackups($type);

        $this->activityLogService->record(
            'backup.' . $type . '.create',
            'backup_history',
            $id,
            'Created ' . strtoupper($type) . ' backup ' . $filename
        );

        return ['id' => $id, 'filename' => $filename, 'size' => $size];
    }

    /**
     * Column names for a table minus the excluded ones. Excluded columns are
     * left out of the SELECT entirely, so their values never enter PHP memory
     * -- defence at the query layer, not the formatting layer.
     *
     * @param  string[] $excludedColumns
     * @return string[]
     */
    private function allowedColumns(string $table, array $excludedColumns): array
    {
        $names = $this->db->getFieldNames($table) ?: [];

        return array_values(array_filter(
            $names,
            static fn (string $name): bool => ! in_array($name, $excludedColumns, true)
        ));
    }

    /**
     * Streams one table row-at-a-time so a large table is never materialised.
     *
     * @param  string[] $columns
     * @return \Generator<int, array<int, mixed>>
     */
    private function rowGenerator(string $table, array $columns): \Generator
    {
        $builder = $this->db->table($table)->select(implode(', ', $columns));

        foreach (self::EXCEL_ROW_FILTERS[$table] ?? [] as $column => $excludedValues) {
            $builder->whereNotIn($column, $excludedValues);
        }

        $query = $builder->get();

        while ($row = $query->getUnbufferedRow('array')) {
            // Everything is emitted as a string: this is a raw snapshot, and
            // a date format without a real DateTimeImmutable value would
            // render as a bare serial number (see ExcelExportService).
            yield array_map(static fn ($v): string => $v === null ? '' : (string) $v, array_values($row));
        }

        $query->freeResult();
    }

    private function countRows(string $table): int
    {
        $builder = $this->db->table($table);

        foreach (self::EXCEL_ROW_FILTERS[$table] ?? [] as $column => $excludedValues) {
            $builder->whereNotIn($column, $excludedValues);
        }

        return $builder->countAllResults();
    }

    /** Removes .sql.tmp files left behind by a crashed run. */
    private function sweepStaleTempFiles(): void
    {
        foreach (glob($this->backupDirectory() . '*.sql.tmp') ?: [] as $stale) {
            if (is_file($stale) && filemtime($stale) < time() - 86400) {
                @unlink($stale);
            }
        }
    }
}
