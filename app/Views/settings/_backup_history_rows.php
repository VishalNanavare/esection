<?php
/**
 * The <tbody> contents of the "Previous backups" table.
 *
 * Extracted so the full page render and the AJAX refresh emit IDENTICAL
 * markup from one source. Building the rows a second time in JavaScript would
 * be the usual way to do this and the usual way for the two to drift -- a
 * badge or a column added here would silently not appear after an AJAX
 * update.
 *
 * @var array $history rows from BackupService::history()
 */
?>
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
                <form action="<?= base_url('settings/backup/delete/' . $row['id']) ?>"
                      method="post" class="d-inline js-delete-backup"
                      data-filename="<?= esc($row['filename'], 'attr') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-glass text-danger"
                            title="Delete this backup">
                        <i class="fa fa-trash"></i> Delete
                    </button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
