<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-database me-2 text-indigo"></i> Backup</h3>
                    <p class="text-muted small mb-0">Take a complete copy of the system, or export the reference data as a spreadsheet.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>
        </div>
    </div>
</div>

<?php
    // Both conditions block a working backup, so surface them BEFORE the
    // button is pressed rather than as an error afterwards.
    $blocked = ! $encryptionAvailable || ! $passwordConfigured;
?>

<?php if (! $encryptionAvailable): ?>
    <div class="row"><div class="col-12">
        <div class="glass-card p-4 mb-4 border border-danger border-opacity-25">
            <h6 class="fw-bold text-danger mb-1"><i class="fa fa-exclamation-triangle me-1"></i> Encryption is not configured on this server</h6>
            <p class="text-muted small mb-0">Backup files cannot be password-protected until this is fixed. Ask your administrator to run <code>php spark key:generate</code> on the server.</p>
        </div>
    </div></div>
<?php elseif (! $passwordConfigured): ?>
    <div class="row"><div class="col-12">
        <div class="glass-card p-4 mb-4 border border-warning border-opacity-25">
            <h6 class="fw-bold text-amber mb-1"><i class="fa fa-info-circle me-1"></i> Set a backup password first</h6>
            <p class="text-muted small mb-0">The database backup is delivered as a password-protected file. Set the password below before creating your first backup.</p>
        </div>
    </div></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <!-- Run backups -->
    <div class="col-lg-7">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-dark mb-3"><i class="fa fa-play-circle me-2 text-indigo"></i> Create a backup</h5>

            <div class="d-flex align-items-start justify-content-between mb-3 pb-3 border-bottom border-secondary border-opacity-10">
                <div class="me-3">
                    <div class="fw-semibold text-dark">Complete system backup (SQL)</div>
                    <p class="text-muted small mb-0">Every table and every row, for restoring the system if something goes wrong. Delivered as a <strong>password-protected</strong> file.</p>
                </div>
                <form action="<?= base_url('settings/backup/sql') ?>" method="post" class="flex-shrink-0 backup-run-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-indigo" <?= $blocked ? 'disabled' : '' ?>>
                        <i class="fa fa-database me-1"></i> Backup now
                    </button>
                </form>
            </div>

            <div class="d-flex align-items-start justify-content-between">
                <div class="me-3">
                    <div class="fw-semibold text-dark">Reference data (Excel)</div>
                    <p class="text-muted small mb-0">
                        Universities, courses, academic years, users and settings, one sheet per table.
                        Excludes student records and passwords, and is <strong>not</strong> password-protected.
                    </p>
                </div>
                <form action="<?= base_url('settings/backup/excel') ?>" method="post" class="flex-shrink-0 backup-run-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-glass">
                        <i class="fa fa-file-excel-o me-1"></i> Export now
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Password + retention -->
    <div class="col-lg-5">
        <div class="glass-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="fa fa-key me-2 text-indigo"></i> Backup password</h5>
                <?php if ($passwordConfigured): ?>
                    <span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> Configured</span>
                <?php else: ?>
                    <span class="badge badge-glass-amber">Not configured</span>
                <?php endif; ?>
            </div>

            <form action="<?= base_url('settings/backup/password/store') ?>" method="post" class="mb-4">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label text-secondary small fw-semibold">New password</label>
                    <input type="password" name="backup_password" class="form-control"
                           minlength="<?= (int) $minPasswordLength ?>" autocomplete="new-password" required>
                </div>
                <div class="mb-2">
                    <label class="form-label text-secondary small fw-semibold">Confirm password</label>
                    <input type="password" name="backup_password_confirm" class="form-control"
                           minlength="<?= (int) $minPasswordLength ?>" autocomplete="new-password" required>
                </div>
                <p class="text-muted small mb-2">
                    At least <?= (int) $minPasswordLength ?> characters. You will need this to open any backup file &mdash;
                    <strong>store it somewhere safe, it cannot be recovered.</strong>
                </p>
                <button type="submit" class="btn btn-indigo btn-sm w-100" <?= $encryptionAvailable ? '' : 'disabled' ?>>
                    <?= $passwordConfigured ? 'Change password' : 'Set password' ?>
                </button>
            </form>

            <form action="<?= base_url('settings/backup/retention/store') ?>" method="post">
                <?= csrf_field() ?>
                <label class="form-label text-secondary small fw-semibold">Backups to keep (per type)</label>
                <div class="input-group">
                    <input type="number" name="backup_retention_count" class="form-control"
                           value="<?= (int) $retentionCount ?>" min="1" max="<?= (int) $maxRetention ?>" required>
                    <button type="submit" class="btn btn-glass">Save</button>
                </div>
                <p class="text-muted small mb-0 mt-2">Older backups are deleted automatically so the disk cannot fill up.</p>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fa fa-history me-2 text-indigo"></i> Previous backups</h5>
            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Type</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Created By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No backups have been created yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $row): ?>
                                <tr>
                                    <td class="small text-muted"><?= esc($row['created_at']) ?></td>
                                    <td>
                                        <?php if ($row['type'] === 'sql'): ?>
                                            <span class="badge badge-glass-indigo"><i class="fa fa-lock me-1"></i> SQL (protected)</span>
                                        <?php else: ?>
                                            <span class="badge badge-glass-emerald">Excel</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small fw-semibold text-dark"><?= esc($row['filename']) ?></td>
                                    <td class="small text-muted">
                                        <?= $row['file_size'] !== null
                                            ? esc(number_format($row['file_size'] / 1024, 1) . ' KB')
                                            : '-' ?>
                                    </td>
                                    <td class="small text-muted"><?= esc($row['created_by'] ?: 'System') ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('settings/backup/download/' . $row['id']) ?>"
                                           class="btn btn-sm btn-glass text-primary">
                                            <i class="fa fa-download"></i> Download
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_backup_js') ?>
<?= $this->endSection() ?>
