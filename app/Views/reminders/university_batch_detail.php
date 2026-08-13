<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-clock-o me-2 text-indigo"></i> <?= esc($batch['university_name']) ?></h3>
                    <p class="text-muted small mb-0">Academic Year <?= esc($batch['academic_year']) ?><?php if (!empty($batch['admission_taken_in'])): ?> &middot; <?= esc($batch['admission_taken_in']) ?><?php endif; ?></p>
                </div>
                <div>
                    <?php if (can('reminders_university.print')): ?>
                        <a href="<?= base_url('reminders/university/pdf/' . $batch['id']) ?>" target="_blank" class="btn btn-emerald me-1"><i class="fa fa-file-pdf-o me-1"></i> View / Reprint PDF</a>
                    <?php endif; ?>
                    <a href="<?= base_url('reminders/university/history') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to History</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Candidate</th>
                            <th>Case No.</th>
                            <th>Reminder History</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No candidates in this batch.</td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($students as $s): ?>
                                <tr>
                                    <td class="fw-semibold text-muted"><?= $i++ ?></td>
                                    <td class="fw-bold text-dark"><?= esc($s['student_name']) ?></td>
                                    <td><span class="badge badge-glass-indigo"><?= esc($s['eligibility_case_no']) ?></span></td>
                                    <td class="small">
                                        <?php foreach ($s['notes'] as $ni => $note): ?>
                                            <?php if ($ni > 0): ?><hr class="my-1"><?php endif; ?>
                                            <?= esc($note['note_text']) ?> &mdash; <?= esc(!empty($note['note_date']) ? date('d/m/Y', strtotime($note['note_date'])) : '') ?>
                                        <?php endforeach; ?>
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
