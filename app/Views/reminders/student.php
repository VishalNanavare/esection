<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-user me-2 text-indigo"></i> Candidate Document Reminder Portal</h3>
                    <p class="text-muted small mb-0">Generate direct reminder notices for candidates with missing original marksheets or migration certificates.</p>
                </div>
                <a href="<?= base_url('reminders/student/history') ?>" class="btn btn-glass"><i class="fa fa-history me-1"></i> Reminder History</a>
            </div>

            <form action="<?= base_url('reminders/generateStudentReminder') ?>" method="post" target="_blank">
                <?= csrf_field() ?>
                <div class="row g-3 mb-4 filter-panel">
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Candidate Full Name</label>
                        <input type="text" name="student_name" class="form-control" placeholder="e.g. Choudhari Amina Mubarakali" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Eligibility Case Number</label>
                        <input type="text" name="eligibility_case_no" class="form-control" placeholder="e.g. CASE-2025/88" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Admitted Course / Stream</label>
                        <input type="text" name="course_name" class="form-control" placeholder="e.g. SYBA / MCOM" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Missing Documents / Remarks</label>
                        <input type="text" name="missing_doc" class="form-control" placeholder="e.g. Original Migration Certificate & Final Year Marksheet" required>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-indigo py-2 px-4">
                        <i class="fa fa-file-pdf-o me-1"></i> Generate Candidate Reminder PDF
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
