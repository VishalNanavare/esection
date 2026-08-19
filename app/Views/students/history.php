<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> Verification Batch History</h3>
                    <p class="text-muted small mb-0">Browse, edit, or delete previously submitted candidate batches.</p>
                </div>
                <div>
                    <?php // Toggle stays a master kill switch; the permission scopes it per user. ?>
                    <?php if (feature_enabled('feature_export_enabled') && can('students.export')): ?>
                        <a href="<?= base_url('students/history/export') ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('students/new') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to New Entry Form</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>University Address</th>
                            <th>Admission Taken In</th>
                            <th>Academic Year</th>
                            <th class="text-center">Candidates</th>
                            <th>Created</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No batches submitted yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $b): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= esc($b['array_space']) ?></td>
                                    <td class="small text-muted"><?= esc_address($b['clg_add']) ?></td>
                                    <td><?= esc($b['admission_taken_in']) ?></td>
                                    <td><?= esc($b['admission_taken_year']) ?></td>
                                    <td class="text-center"><span class="badge badge-glass-indigo"><?= (int) $b['student_count'] ?></span></td>
                                    <td class="small text-muted"><?= esc(is_numeric($b['en_time']) ? date('d/m/Y H:i', (int) $b['en_time']) : $b['en_time']) ?></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('students/batch/' . urlencode($b['array_space'])) ?>" class="btn btn-sm btn-glass text-primary me-1">
                                            <i class="fa fa-eye"></i> View
                                        </a>
                                        <?php // Both routes require students.print. These open in a new
                                              // tab, so AccessFilter takes its non-JSON branch and the tab
                                              // shows the dashboard with an error -- which reads as a broken
                                              // link rather than a withheld one. ?>
                                        <?php if (can('students.print')): ?>
                                            <a href="<?= base_url('pdf/dispatch/' . urlencode($b['array_space'])) ?>" target="_blank" class="btn btn-sm btn-glass text-emerald me-1">
                                                <i class="fa fa-file-pdf-o"></i> PDF
                                            </a>
                                            <a href="<?= base_url('pdf/dispatchAccounts/' . urlencode($b['array_space'])) ?>" target="_blank" class="btn btn-sm btn-glass text-emerald">
                                                <i class="fa fa-file-pdf-o"></i> AC View
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
