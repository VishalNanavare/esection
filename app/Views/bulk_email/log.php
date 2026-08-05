<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-list-alt me-2 text-indigo"></i> Sent Emails</h3>
                    <p class="text-muted small mb-0">Every email the system has sent, with its delivery status.</p>
                </div>
                <div>
                    <?php if (($counts['failed'] ?? 0) > 0): ?>
                        <form action="<?= base_url('bulk-email/retryAllFailed') ?>" method="post" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-glass text-danger me-1">
                                <i class="fa fa-refresh me-1"></i> Retry all failed (<?= (int) $counts['failed'] ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= base_url('bulk-email') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Send Emails</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <form action="<?= base_url('bulk-email/log') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="" <?= $status === '' ? 'selected' : '' ?>>-- All (<?= (int) (($counts['sent'] ?? 0) + ($counts['failed'] ?? 0)) ?>) --</option>
                        <option value="sent" <?= $status === 'sent' ? 'selected' : '' ?>>Sent (<?= (int) ($counts['sent'] ?? 0) ?>)</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed (<?= (int) ($counts['failed'] ?? 0) ?>)</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Search name or email</label>
                    <input type="text" name="q" class="form-control" value="<?= esc($search) ?>" placeholder="e.g. gujaratuniversity.ac.in">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-indigo w-100"><i class="fa fa-search me-1"></i> Search</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>Date</th><th>To</th><th>Type</th><th>Subject</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                <?= ($status || $search) ? 'No emails match the current filters.' : 'No emails have been sent yet.' ?>
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td class="small text-muted"><?= esc($r['created_at']) ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= esc($r['recipient_name'] ?: '-') ?></div>
                                        <div class="small text-muted"><?= esc($r['recipient_email']) ?></div>
                                    </td>
                                    <td class="small text-muted"><?= esc(ucfirst((string) $r['recipient_type'])) ?></td>
                                    <td class="small"><?= esc($r['subject'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($r['status'] === 'sent'): ?>
                                            <span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> Sent</span>
                                        <?php else: ?>
                                            <span class="badge badge-glass-indigo text-danger" title="<?= esc($r['error_message'] ?: '') ?>">
                                                <i class="fa fa-exclamation-circle me-1"></i> Failed
                                            </span>
                                            <?php if (!empty($r['error_message'])): ?>
                                                <div class="small text-muted mt-1" style="max-width:260px;"><?= esc(mb_strimwidth((string) $r['error_message'], 0, 120, '...')) ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ((int) $r['attempts'] > 1): ?>
                                            <div class="small text-muted"><?= (int) $r['attempts'] ?> attempts</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($r['status'] === 'failed'): ?>
                                            <form action="<?= base_url('bulk-email/retry/' . $r['id']) ?>" method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-glass text-primary"><i class="fa fa-refresh"></i> Retry</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($pager)): ?>
                <?= $pager->links('default', 'glass') ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
