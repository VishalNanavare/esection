<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> Verification Batch History</h3>
                    <p class="text-muted small mb-0">Browse, edit, or delete previously submitted candidate batches.</p>
                </div>
                <div>
                    <?php // Toggle stays a master kill switch; the permission scopes it per user. ?>
                    <?php if (feature_enabled('feature_export_enabled') && can('students.export')): ?>
                        <?php /* Carries the current filters, so the export is what the
                                 screen is showing rather than the whole table. */ ?>
                        <a href="<?= base_url('students/history/export?' . http_build_query($filters)) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('students/new') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to New Entry Form</a>
                </div>
            </div>

            <?php /* Every filter optional -- blank means no restriction, so the
                     screen still opens on real data rather than an empty prompt
                     to search first. The same query string drives the export,
                     so the file always matches what is on screen. */ ?>
            <form action="<?= base_url('students/history') ?>" method="get" class="row g-3 mb-4 filter-panel" id="batch_history_filters">
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Admission Year</label>
                    <select name="year" class="form-select select2-ajax-academic-year">
                        <?php if ($filters['year'] !== ''): ?>
                            <option value="<?= esc($filters['year']) ?>" selected><?= esc($filters['year']) ?></option>
                        <?php else: ?>
                            <option value="">-- All Years --</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">University</label>
                    <input type="text" name="university" class="form-control" placeholder="Any part of the name or address"
                           value="<?= esc($filters['university']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Batch No.</label>
                    <input type="text" name="batch" class="form-control" placeholder="e.g. esection1_47"
                           value="<?= esc($filters['batch']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Candidate Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Any part of the name"
                           value="<?= esc($filters['name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Created From</label>
                    <input type="text" name="date_from" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($filters['date_from']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Created To</label>
                    <input type="text" name="date_to" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($filters['date_to']) ?>">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="<?= base_url('students/history') ?>" class="btn btn-glass"><i class="fa fa-refresh me-1"></i> Reset</a>
                    <button type="submit" class="btn btn-indigo px-4"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted" id="batch_history_count">
                    <?php if ($pager !== null && $pager->getTotal() > 0): ?>
                        <?= number_format($pager->getTotal()) ?> batch<?= $pager->getTotal() === 1 ? '' : 'es' ?>
                    <?php endif; ?>
                </small>
                <small class="text-muted d-none" id="batch_history_loading">
                    <i class="fa fa-circle-o-notch fa-spin me-1"></i> Loading...
                </small>
            </div>

            <div class="table-responsive position-relative" id="batch_history_wrap">
                <table class="table table-glass align-middle mb-0" id="batch_history_table">
                    <thead>
                        <tr>
                            <th>Batch / Created</th>
                            <th>Target University</th>
                            <th>Course &amp; Year</th>
                            <th class="text-center">Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="batch_history_rows">
                        <?= $this->include('students/_history_rows') ?>
                    </tbody>
                </table>
            </div>

            <?php /* Rendered by the same 'glass' template the AJAX reply uses,
                     so a paged view cannot drift from a freshly loaded one. */ ?>
            <div id="batch_history_pager">
                <?php if ($pager !== null): ?>
                    <?= $pager->links('default', 'glass') ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_students_history_js') ?>
<?= $this->endSection() ?>

