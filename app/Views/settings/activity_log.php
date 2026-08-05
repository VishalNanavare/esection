<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-list-alt me-2 text-indigo"></i> Activity Log</h3>
                    <p class="text-muted small mb-0">The most recent 100 settings changes, newest first.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entries)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No settings changes recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td class="small text-muted"><?= esc($entry['created_at']) ?></td>
                                    <td class="fw-semibold text-dark"><?= esc($entry['username'] ?: 'System') ?></td>
                                    <td><span class="badge badge-glass-indigo"><?= esc($entry['action']) ?></span></td>
                                    <td class="small text-muted"><?= esc($entry['description']) ?></td>
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
