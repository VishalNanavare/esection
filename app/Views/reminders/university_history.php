<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> University Reminder History</h3>
                    <p class="text-muted small mb-0">Browse ongoing reminder batches, grouped by university and academic year.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled') && can('reminders_university.export')): ?>
                        <a href="<?= base_url('reminders/university/history/export') ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('reminders/university') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Reminder Portal</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>University</th>
                            <th>Academic Year</th>
                            <th>Course</th>
                            <th class="text-center">Candidates</th>
                            <th>Started</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No reminder batches yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $b): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= esc($b['university_name']) ?></td>
                                    <td><?= esc($b['academic_year']) ?></td>
                                    <td><?= esc($b['admission_taken_in'] ?: '-') ?></td>
                                    <td class="text-center"><span class="badge badge-glass-indigo"><?= (int) $b['student_count'] ?></span></td>
                                    <td class="small text-muted"><?= esc($b['created_at']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('reminders/university/batch/' . $b['id']) ?>" class="btn btn-sm btn-glass text-primary me-1">
                                            <i class="fa fa-eye"></i> View
                                        </a>
                                        <?php if (can('reminders_university.print')): ?>
                                            <a href="<?= base_url('reminders/university/pdf/' . $b['id']) ?>" target="_blank" class="btn btn-sm btn-glass text-emerald">
                                                <i class="fa fa-file-pdf-o"></i> PDF
                                            </a>
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
