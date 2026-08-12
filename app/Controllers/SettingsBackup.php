<?php

namespace App\Controllers;

use App\Services\ActivityLogService;
use App\Services\BackupPasswordService;
use App\Services\BackupService;

class SettingsBackup extends BaseController
{
    protected BackupService $backupService;
    protected BackupPasswordService $backupPasswordService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->backupService         = new BackupService();
        $this->backupPasswordService = new BackupPasswordService();
        $this->activityLogService    = new ActivityLogService();
    }

    public function index()
    {
        $data = [
            'title'               => 'Settings — Backup',
            'history'             => $this->backupService->history(50),
            'passwordConfigured'  => $this->backupPasswordService->isConfigured(),
            'encryptionAvailable' => $this->backupPasswordService->isEncryptionAvailable(),
            'retentionCount'      => $this->backupPasswordService->getRetentionCount(),
            'minPasswordLength'   => BackupPasswordService::MIN_LENGTH,
            'maxRetention'        => BackupPasswordService::MAX_RETENTION,
        ];

        return view('settings/backup', $data);
    }

    public function runSql()
    {
        // A growing database means a growing dump; a timeout partway through
        // ZipArchive::close() is the one way to strand a partial archive.
        set_time_limit(0);

        try {
            $result = $this->backupService->createSqlBackup();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return redirect()->to(base_url('settings/backup'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsBackup::runSql] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/backup'))
                ->with('error', 'The backup could not be completed. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/backup'))->with(
            'success',
            'Complete system backup created: ' . $result['filename']
            . ' (' . $this->formatBytes($result['size']) . '). '
            . 'This file is password-protected -- use your configured backup password to open it.'
        );
    }

    public function runExcel()
    {
        set_time_limit(0);

        try {
            $result = $this->backupService->createExcelBackup();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return redirect()->to(base_url('settings/backup'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsBackup::runExcel] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/backup'))
                ->with('error', 'The Excel backup could not be completed. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/backup'))->with(
            'success',
            'Excel data backup created: ' . $result['filename']
            . ' (' . $this->formatBytes($result['size']) . ', ' . $result['sheets'] . ' sheets). '
            . 'This file is NOT password-protected -- store it somewhere safe.'
        );
    }

    public function download($id)
    {
        try {
            $file = $this->backupService->resolveDownload((int) $id);
        } catch (\RuntimeException $e) {
            return redirect()->to(base_url('settings/backup'))->with('error', $e->getMessage());
        }

        // The SQL archive is the entire database, so who took a copy and when
        // is exactly the question an audit asks.
        $this->activityLogService->record(
            'backup.download',
            'backup_history',
            (int) $id,
            'Downloaded backup ' . $file['filename']
        );

        return $this->response->download($file['path'], null)->setFileName($file['filename']);
    }

    /**
     * Remove one backup -- file and history row.
     *
     * POST, not GET: this destroys a recovery point, so it must not be
     * reachable by a link, a prefetch or a crawler. The route sits in the
     * adminFilter group and CSRF applies to POST, so the same guards the rest
     * of this screen relies on cover it.
     */
    public function delete($id)
    {
        try {
            $filename = $this->backupService->deleteBackup((int) $id);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return redirect()->to(base_url('settings/backup'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsBackup::delete] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/backup'))
                ->with('error', 'The backup could not be deleted. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/backup'))
            ->with('success', 'Backup deleted: ' . $filename);
    }

    public function storePassword()
    {
        try {
            $this->backupPasswordService->save(
                (string) $this->request->getPost('backup_password'),
                (string) $this->request->getPost('backup_password_confirm')
            );
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return redirect()->to(base_url('settings/backup'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsBackup::storePassword] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/backup'))
                ->with('error', 'The backup password could not be saved. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/backup'))
            ->with('success', 'Backup password saved. Keep it safe -- backup files cannot be opened without it.');
    }

    public function storeRetention()
    {
        try {
            $this->backupPasswordService->saveRetentionCount((int) $this->request->getPost('backup_retention_count'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/backup'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('settings/backup'))->with('success', 'Backup retention updated.');
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }

        return number_format($bytes / 1024, 1) . ' KB';
    }
}
