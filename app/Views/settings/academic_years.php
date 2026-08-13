<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-calendar me-2 text-indigo"></i> Academic Years</h3>
                    <p class="text-muted small mb-0">Add academic years and mark the current one. The current year becomes the default across the system.</p>
                </div>
                <div>
                    <a href="<?= base_url('settings') ?>" class="btn btn-glass me-2"><i class="fa fa-arrow-left me-1"></i> Back</a>
                    <button type="button" class="btn btn-indigo" data-bs-toggle="modal" data-bs-target="#addYearModal">
                        <i class="fa fa-plus me-1"></i> Add Academic Year
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass" id="academic_year_table">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Academic Year</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="academic_year_rows">
                        <?= $this->include('settings/_academic_years_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Academic Year -->
<div class="modal fade" id="addYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-calendar me-2 text-indigo"></i> Add Academic Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('settings/academic-years/store') ?>" method="post" class="js-ajax"
                  data-title="Academic Years" data-refresh="#academic_year_rows" data-close-modal="#addYearModal"
                  data-reset-on-success="1" data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Academic Year</label>
                            <input type="text" name="year_label" class="form-control" placeholder="e.g. 2025-2026" required>
                        </div>
                        <div class="col-12 form-check mt-2">
                            <input type="checkbox" name="is_current" value="1" class="form-check-input" id="add_is_current">
                            <label class="form-check-label small" for="add_is_current">Mark as the current academic year</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Save Academic Year</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Academic Year -->
<div class="modal fade" id="editYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit Academic Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_year_form" method="post" class="js-ajax"
                  data-title="Academic Years" data-refresh="#academic_year_rows" data-close-modal="#editYearModal"
                  data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Academic Year</label>
                            <input type="text" name="year_label" id="edit_year_label" class="form-control" required>
                        </div>
                        <div class="col-12 form-check mt-2">
                            <input type="checkbox" name="is_current" value="1" class="form-check-input" id="edit_is_current">
                            <label class="form-check-label small" for="edit_is_current">Mark as the current academic year</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Update Academic Year</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_academic_years_js') ?>
<?= $this->endSection() ?>
