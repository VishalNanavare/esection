<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-toggle-on me-2 text-indigo"></i> Feature Toggles</h3>
                    <p class="text-muted small mb-0">Turn features on or off across the system. Switching a feature off hides it for everyone and blocks the action, not just the button.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>

            <form action="<?= base_url('settings/features/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="list-group">
                    <?php foreach ($flags as $key => $flag): ?>
                        <label class="list-group-item d-flex align-items-center justify-content-between">
                            <span class="fw-semibold text-dark"><?= esc($flag['label']) ?></span>
                            <input type="checkbox" class="form-check-input" name="<?= esc($key) ?>" value="1" <?= $flag['enabled'] ? 'checked' : '' ?>>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-indigo"><i class="fa fa-check me-1"></i> Save Feature Toggles</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
