<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fa fa-plus-circle me-2 text-gradient-indigo"></i> New Student Verification Form</h3>
                    <p class="text-muted small mb-0">Create eligibility verification dispatch cases for target universities across India.</p>
                </div>
                <div>
                    <a href="<?= base_url('students/history') ?>" class="btn btn-glass me-2"><i class="fa fa-history me-1"></i> Batch History</a>
                    <span class="badge badge-glass-indigo fs-6">Batch #: <span id="display_common_no"><?= esc($common_no) ?></span></span>
                </div>
            </div>

            <!-- University & Program Details with AJAX Select2 -->
            <div class="row g-3 mb-4 filter-panel">
                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">Target University Name (AJAX Search)</label>
                    <select class="form-select select2-ajax-college" id="clg_add_select" required>
                        <option value="" selected>-- Type or Select Target University --</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">To (Addressed Authority Title)</label>
                    <input type="text" class="form-control" id="to" placeholder="e.g. The Controller of Examinations">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">Academic Admission Year (AJAX)</label>
                    <select class="form-select select2-ajax-academic-year" id="admission_taken_year" required>
                        <option value="" selected>-- Select Academic Year --</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">Admitted Program / Stream (AJAX)</label>
                    <select class="form-select select2-ajax-stream" id="admission_taken_in" required>
                        <option value="" selected>-- Select Academic Program --</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label text-secondary small fw-semibold">Favoring Authority Payment Instruction (In Favour Of)</label>
                    <input type="text" class="form-control" id="In_Favour_of" placeholder="Auto-populated payment title">
                </div>

                <div class="col-12">
                    <label class="form-label text-secondary small fw-semibold">Full Postal Address</label>
                    <textarea class="form-control" id="clg_add" rows="2" placeholder="University full postal address"></textarea>
                </div>
            </div>

            <!-- Single Candidate Input Entry Panel -->
            <h5 class="fw-bold mb-3"><i class="fa fa-user-plus me-2 text-emerald"></i> Add Candidate to Batch Dispatch</h5>
            <div class="row g-3 mb-4 entry-panel">
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="stud_name" placeholder="Student Full Name">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="stud_nee_name" placeholder="Nee / Maiden Name (Optional)">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="eligibility_case_no" placeholder="Eligibility Case No.">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="verification_by_you" placeholder="Verification Remarks">
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-emerald" id="btn_add_student">
                        <i class="fa fa-plus me-1"></i> Add Candidate to List
                    </button>
                </div>
            </div>

            <!-- Candidate Batch Table -->
            <div class="table-responsive mb-4">
                <table class="table table-glass table-sticky-id" id="student_batch_table">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Student Full Name</th>
                            <th>Nee / Maiden Name</th>
                            <th>Eligibility Case No.</th>
                            <th>Verification Remarks</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="empty_row">
                            <td colspan="6" class="text-center text-muted py-4">No candidates added to this batch yet. Use the form above to add students.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Action Footer -->
            <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary border-opacity-25">
                <a href="<?= base_url('dashboard') ?>" class="btn btn-glass">
                    <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
                </a>

                <button type="button" class="btn btn-indigo" id="btn_save_and_pdf">
                    <i class="fa fa-file-pdf-o me-1"></i> Create PDF & Save Dispatch Case
                </button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_students_new_js') ?>
<?= $this->endSection() ?>
