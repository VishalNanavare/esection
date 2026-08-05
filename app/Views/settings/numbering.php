<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-hashtag me-2 text-indigo"></i> Document Numbering</h3>
                    <p class="text-muted small mb-0">Set the prefix used on new case numbers. This previews the format only — each number's last four digits are assigned at random, not in sequence.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>

            <form action="<?= base_url('settings/numbering/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Case Number Prefix</label>
                        <input type="text" name="case_no_prefix" id="case_no_prefix" class="form-control" value="<?= esc($prefix) ?>" required maxlength="20">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Example (format preview, not the next real number)</label>
                        <input type="text" class="form-control" id="preview_example" value="<?= esc($preview) ?>" disabled>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-indigo"><i class="fa fa-check me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_numbering_js') ?>
<?= $this->endSection() ?>
