<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-users me-2 text-indigo"></i> Batch <?= esc($arraySpace) ?></h3>
                    <p class="text-muted small mb-0">Edit or remove a candidate from this verification batch.</p>
                </div>
                <div>
                    <?php if (can('students.print')): ?>
                        <a href="<?= base_url('pdf/dispatch/' . urlencode($arraySpace)) ?>" target="_blank" class="btn btn-emerald me-1"><i class="fa fa-file-pdf-o me-1"></i> Uni View</a>
                        <a href="<?= base_url('pdf/dispatchAccounts/' . urlencode($arraySpace)) ?>" target="_blank" class="btn btn-emerald me-1"><i class="fa fa-file-pdf-o me-1"></i> AC View</a>
                    <?php endif; ?>
                    <a href="<?= base_url('students/history') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to History</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Candidate</th>
                            <th>Nee Name</th>
                            <th>Case No.</th>
                            <th>Verification Remark</th>
                            <th>Email</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="batch_student_rows">
                        <?= $this->include('students/_batch_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Candidate -->
<div class="modal fade" id="editStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit Candidate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_student_form" method="post" class="js-ajax"
                  data-title="Candidates" data-refresh="#batch_student_rows" data-close-modal="#editStudentModal"
                  data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Student Full Name</label>
                            <input type="text" name="student_name" id="edit_student_name" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Nee / Maiden Name</label>
                            <input type="text" name="student_nee_name" id="edit_student_nee_name" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Eligibility Case No.</label>
                            <input type="text" name="eligibility_case_no" id="edit_eligibility_case_no" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Verification Remark</label>
                            <input type="text" name="verification_of_marksheet_done_by_you" id="edit_verification" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Email (Optional)</label>
                            <input type="email" name="email" id="edit_student_email" class="form-control">
                        </div>
                    </div>
                    <p class="text-muted small mt-3 mb-0">University, academic year, and admission course are fixed for this batch and can't be changed here.</p>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Update Candidate</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_students_history_js') ?>
<?= $this->endSection() ?>
