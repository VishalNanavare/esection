<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> Candidate Reminder History</h3>
                    <p class="text-muted small mb-0">Browse and reprint previously generated candidate document reminder letters.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled')): ?>
                        <a href="<?= base_url('reminders/student/history/export') ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('reminders/student') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Reminder Portal</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Candidate</th>
                            <th>Case No.</th>
                            <th>Course</th>
                            <th>Missing Document(s)</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="student_reminder_rows">
                        <?= $this->include('reminders/_student_history_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_student_reminder_history_js') ?>
<?= $this->endSection() ?>
