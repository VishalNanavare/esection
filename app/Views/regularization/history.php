<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> Regularization Letter History</h3>
                    <p class="text-muted small mb-0">Browse, edit, reprint, or delete previously generated regularization letters.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled') && can('regularization.export')): ?>
                        <a href="<?= base_url('regularization/history/export?' . http_build_query($filters)) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('regularization') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Regularization Form</a>
                </div>
            </div>

            <?php /* Every filter optional -- see students/history. */ ?>
            <form action="<?= base_url('regularization/history') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Candidate Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Any part of the name"
                           value="<?= esc($filters['name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Eligibility Case No.</label>
                    <input type="text" name="case_no" class="form-control" placeholder="e.g. IDOL/2026"
                           value="<?= esc($filters['case_no']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">University / Board</label>
                    <input type="text" name="university" class="form-control" placeholder="Any part of the name"
                           value="<?= esc($filters['university']) ?>">
                </div>
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
                    <a href="<?= base_url('regularization/history') ?>" class="btn btn-glass"><i class="fa fa-refresh me-1"></i> Reset</a>
                    <button type="submit" class="btn btn-indigo px-4"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Student Name</th>
                            <th>University / Board</th>
                            <th>Admission Taken In</th>
                            <th>Academic Year</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="regularization_rows">
                        <?= $this->include('regularization/_history_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Regularization -->
<div class="modal fade" id="editRegModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit Regularization Letter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_reg_form" method="post" class="js-ajax"
                  data-title="Regularization" data-refresh="#regularization_rows" data-close-modal="#editRegModal"
                  data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Admission Letter Addressed To</label>
                            <input type="text" name="admission_letter_for" id="edit_reg_admission_letter_for" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Admission Letter Date</label>
                            <input type="text" name="admission_letter_date" id="edit_reg_admission_letter_date" class="form-control es-datepicker" autocomplete="off" placeholder="YYYY-MM-DD" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Passing Course / Program</label>
                            <input type="text" name="admission_passing_course" id="edit_reg_passing_course" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Last Attended University</label>
                            <input type="text" name="clg_add" id="edit_reg_clg_add" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Admission Taken In (IDOL)</label>
                            <input type="text" name="admission_taken_in" id="edit_reg_admission_taken_in" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Academic Year</label>
                            <input type="text" name="admission_taken_year" id="edit_reg_admission_taken_year" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small fw-semibold">Gender</label>
                            <select name="gender" id="edit_reg_gender" class="form-select" required>
                                <option value="Mr">Mr.</option>
                                <option value="Ms">Ms.</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small fw-semibold">Student Full Name</label>
                            <input type="text" name="student_name" id="edit_reg_student_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Eligibility Case Number</label>
                            <input type="text" name="eligibility_case_no" id="edit_reg_eligibility_case_no" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Update Letter</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_regularization_history_js') ?>
<?= $this->endSection() ?>
