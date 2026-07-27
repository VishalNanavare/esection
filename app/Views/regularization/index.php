<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-file-text-o me-2 text-indigo"></i> Student Eligibility Regularization Form</h3>
                    <p class="text-muted small mb-0">Generate official regularization verification letters for candidates requiring eligibility adjustments.</p>
                </div>
            </div>

            <form action="<?= base_url('regularization/generateLetter') ?>" method="post" target="_blank">
                <?= csrf_field() ?>
                <div class="row g-3 mb-4 filter-panel">
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Admission Letter Addressed To</label>
                        <input type="text" name="admission_letter_for" class="form-control" placeholder="e.g. The Principal / Director" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Admission Letter Date</label>
                        <input type="date" name="admission_letter_date" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Passing Course / Program (AJAX)</label>
                        <select name="admission_passing_course" class="form-select select2-ajax-stream" required>
                            <option value="" selected>-- Select Passing Course --</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Last Attended University (AJAX)</label>
                        <select name="clg_add" class="form-select select2-ajax-college" required>
                            <option value="" selected>-- Select University Name --</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Student Full Name</label>
                        <input type="text" name="student_name" class="form-control" placeholder="e.g. Rahul Sharma" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Eligibility Case Number</label>
                        <input type="text" name="eligibility_case_no" class="form-control" placeholder="e.g. CASE-2025/104" required>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-indigo py-2 px-4">
                        <i class="fa fa-file-pdf-o me-1"></i> Generate Regularization PDF Letter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_regularization_js') ?>
<?= $this->endSection() ?>
