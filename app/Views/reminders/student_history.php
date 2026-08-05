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
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No candidate reminders recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sr = 1; foreach ($records as $r): ?>
                                <tr>
                                    <td class="fw-semibold text-muted"><?= $sr++ ?></td>
                                    <td class="fw-bold text-dark"><?= esc($r['student_name']) ?></td>
                                    <td><span class="badge badge-glass-indigo"><?= esc($r['eligibility_case_no']) ?></span></td>
                                    <td><?= esc($r['course_name']) ?></td>
                                    <td class="small text-muted"><?= esc($r['missing_doc']) ?></td>
                                    <td class="small text-muted"><?= esc($r['created_at']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('reminders/student/pdf/' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-glass text-primary me-1" title="View / reprint PDF">
                                            <i class="fa fa-file-pdf-o"></i> PDF
                                        </a>
                                        <?php if (feature_enabled('feature_delete_enabled')): ?>
                                        <form action="<?= base_url('reminders/student/delete/' . $r['id']) ?>" method="post" class="d-inline delete-student-rem-form" data-name="<?= esc($r['student_name']) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-glass text-danger" title="Delete">
                                                <i class="fa fa-trash"></i> Delete
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
