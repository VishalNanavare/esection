<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fa fa-file-excel-o me-2 text-gradient-indigo"></i> Import Candidates from Excel</h3>
                    <p class="text-muted small mb-0">Upload the admission system's export. Nothing is saved until you review the preview and confirm.</p>
                </div>
                <div>
                    <a href="<?= base_url('students/import/template') ?>" class="btn btn-glass me-2">
                        <i class="fa fa-download me-1"></i> Download Template
                    </a>
                    <a href="<?= base_url('students/new') ?>" class="btn btn-glass">
                        <i class="fa fa-plus-circle me-1"></i> Enter Manually
                    </a>
                </div>
            </div>

            <!-- Step 1: choose the file -->
            <div class="row g-3 mb-2 filter-panel align-items-end">
                <div class="col-md-8">
                    <label class="form-label text-secondary small fw-semibold">Excel file (.xlsx)</label>
                    <input type="file" class="form-control" id="import_file" accept=".xlsx"
                           data-max-kb="<?= (int) $maxSizeKb ?>">
                    <small class="text-muted">
                        Up to <?= (int) ($maxSizeKb / 1024) ?>MB. Columns are matched by heading, so extra columns are
                        ignored and the order does not matter. Each certifying body becomes its own dispatch batch,
                        up to <?= (int) $maxBatch ?> candidates per batch.
                    </small>
                </div>
                <div class="col-md-4">
                    <button type="button" class="btn btn-indigo w-100 py-2" id="btn_preview">
                        <i class="fa fa-search me-1"></i> Preview
                    </button>
                </div>
            </div>
        </div>

        <!-- Step 2: mapping -- hidden until a preview exists -->
        <div class="glass-card p-4 mb-4" id="mapping_card" style="display:none;">
            <h5 class="fw-bold text-dark mb-1"><i class="fa fa-random me-2 text-indigo"></i> Match the file to your records</h5>
            <p class="text-muted small mb-3">
                The export names universities and courses its own way. Set each one once &mdash; the choices are
                remembered, so the next file only asks about values it has not seen before.
            </p>

            <h6 class="fw-semibold text-dark mt-3 mb-2">Certifying bodies &rarr; University</h6>
            <div class="table-responsive mb-4">
                <table class="table table-glass" id="board_map_table">
                    <thead>
                        <tr>
                            <th>In the file</th>
                            <th class="text-center">Rows</th>
                            <th>Send the verification request to</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <h6 class="fw-semibold text-dark mb-2">Courses &rarr; Stream</h6>
            <div class="table-responsive">
                <table class="table table-glass" id="course_map_table">
                    <thead>
                        <tr>
                            <th>In the file</th>
                            <th class="text-center">Rows</th>
                            <th>Record the batch under</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="text-end mt-3">
                <button type="button" class="btn btn-glass" id="btn_reapply">
                    <i class="fa fa-refresh me-1"></i> Apply mappings
                </button>
            </div>
        </div>

        <!-- Step 3: preview -->
        <div class="glass-card p-4" id="preview_card" style="display:none;">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h5 class="fw-bold text-dark mb-1"><i class="fa fa-list-ul me-2 text-indigo"></i> Preview</h5>
                    <p class="text-muted small mb-0" id="preview_summary"></p>
                </div>
                <div>
                    <span class="badge badge-glass-emerald me-1" id="count_ready">0 ready</span>
                    <span class="badge badge-glass-amber me-1" id="count_warning">0 warnings</span>
                    <span class="badge badge-glass-indigo me-1" id="count_record">0 record only</span>
                    <span class="badge badge-glass-rose" id="count_error">0 errors</span>
                </div>
            </div>

            <div class="alert alert-warning small" id="oversized_notice" style="display:none;"></div>

            <div class="table-responsive mb-3">
                <table class="table table-glass table-sticky-id" id="preview_table">
                    <thead>
                        <tr>
                            <th class="col-sr">Row</th>
                            <th>Status</th>
                            <th>Candidate</th>
                            <th>Case No.</th>
                            <th>Certifying Body</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary border-opacity-25">
                <a href="<?= base_url('students/history') ?>" class="btn btn-glass">
                    <i class="fa fa-arrow-left me-1"></i> Batch History
                </a>
                <button type="button" class="btn btn-emerald" id="btn_commit">
                    <i class="fa fa-check me-1"></i> <span id="commit_label">Import</span>
                </button>
            </div>
        </div>

        <!-- Step 4: result -->
        <div class="glass-card p-4" id="result_card" style="display:none;">
            <h5 class="fw-bold text-dark mb-3"><i class="fa fa-check-circle me-2 text-emerald"></i> Import complete</h5>
            <p class="text-muted small" id="result_summary"></p>
            <div class="table-responsive">
                <table class="table table-glass" id="result_table">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>University</th>
                            <th class="text-center">Candidates</th>
                            <th class="text-end">Dispatch Letters</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_students_import_js') ?>
<?= $this->endSection() ?>
