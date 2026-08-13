<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-check-square-o me-2 text-indigo"></i> Eligibility Confirmation Portal</h3>
                    <p class="text-muted small mb-0">Confirm Migration/TC, Passing/Degree, and Statement of Marks status for verified candidates.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled') && can('confirmations.export')): ?>
                        <a href="<?= base_url('confirmations/export?year=' . urlencode($selected_year) . '&stream=' . urlencode($selected_stream)) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('confirmations/history') ?>" class="btn btn-glass"><i class="fa fa-history me-1"></i> Confirmation History</a>
                </div>
            </div>

            <!-- Filter Panel with AJAX Select2 -->
            <form action="<?= base_url('confirmations') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Academic Admission Year (AJAX, optional)</label>
                    <select name="year" class="form-select select2-ajax-academic-year">
                        <?php if ($selected_year): ?>
                            <option value="<?= esc($selected_year) ?>" selected><?= esc($selected_year) ?></option>
                        <?php else: ?>
                            <option value="">-- All Years --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Program / Academic Stream (AJAX, optional)</label>
                    <select name="stream" class="form-select select2-ajax-stream">
                        <?php if ($selected_stream): ?>
                            <option value="<?= esc($selected_stream) ?>" selected><?= esc($selected_stream) ?></option>
                        <?php else: ?>
                            <option value="">-- All Streams --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-semibold">&nbsp;</label>
                    <button type="submit" class="btn btn-indigo w-100 py-2">
                        <i class="fa fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>

            <div id="confirmation_pending_region">
                <?= $this->include('confirmations/_pending_region') ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_confirmations_js') ?>
<?= $this->endSection() ?>
