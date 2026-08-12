<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-clock-o me-2 text-indigo"></i> University Verification Reminder Portal</h3>
                    <p class="text-muted small mb-0">Generate 1st and 2nd reminder notice PDF letters for pending eligibility verifications.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled')): ?>
                        <a href="<?= base_url('reminders/university/export?acd_year=' . urlencode($selected_year) . '&stream=' . urlencode($selected_stream) . '&clg_add=' . urlencode($selected_colg)) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('reminders/university/history') ?>" class="btn btn-glass me-1">
                        <i class="fa fa-history me-1"></i> Reminder History
                    </a>
                    <a href="<?= base_url('reminders/student') ?>" class="btn btn-glass">
                        <i class="fa fa-user me-1"></i> Switch to Candidate Reminders
                    </a>
                </div>
            </div>

            <!-- Search Filter Bar with AJAX Select2 -->
            <form action="<?= base_url('reminders/university') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Academic Year (AJAX, optional)</label>
                    <select name="acd_year" class="form-select select2-ajax-academic-year">
                        <?php if ($selected_year): ?>
                            <option value="<?= esc($selected_year) ?>" selected><?= esc($selected_year) ?></option>
                        <?php else: ?>
                            <option value="">-- All Years --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Academic Program / Stream (AJAX, optional)</label>
                    <select name="stream" class="form-select select2-ajax-stream">
                        <?php if ($selected_stream): ?>
                            <option value="<?= esc($selected_stream) ?>" selected><?= esc($selected_stream) ?></option>
                        <?php else: ?>
                            <option value="">-- All Streams --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">University Filter (AJAX Optional)</label>
                    <select name="clg_add" class="form-select select2-ajax-college">
                        <?php if ($selected_colg): ?>
                            <option value="<?= esc($selected_colg) ?>" selected><?= esc($selected_colg) ?></option>
                        <?php else: ?>
                            <option value="">-- All Target Universities --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-indigo px-4">
                        <i class="fa fa-search me-1"></i> Filter Pending Cases
                    </button>
                </div>
            </form>

            <?php if (!empty($students)): ?>
                <?php
                    // See regularization/index.php. The candidate list itself is
                    // unchanged by this POST -- the source query never touches
                    // the reminder tables -- so there is no refresh region.
                ?>
                <form action="<?= base_url('reminders/generateUniversityReminder') ?>" method="post" target="_blank"
                      class="js-ajax-pdf" data-title="University reminder"
                      data-busy-button="Generating..."
                      data-busy-title="Generating the reminder..."
                      data-busy-text="Recording the notes and rendering the PDF. A large batch can take a few moments.">
                    <?= csrf_field() ?>
                    
                    <div class="row g-3 mb-4 entry-panel">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Reminder Notice Type</label>
                            <select name="reminder_type" class="form-select" required>
                                <option value="1st Reminder" selected>1st Reminder Notice</option>
                                <option value="2nd Reminder">2nd Reminder Notice</option>
                                <option value="Final Urgent Reminder">Final Urgent Reminder Notice</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Head Name (To)</label>
                            <input type="text" name="head_name" class="form-control" placeholder="The Controller of Examinations">
                        </div>
                    </div>

                    <!-- Student Selection Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-glass table-sticky-id">
                            <thead>
                                <tr>
                                    <th class="col-sr"><input type="checkbox" id="check_all"></th>
                                    <th>Candidate Name</th>
                                    <th>Eligibility Case No.</th>
                                    <th>Target University Address</th>
                                    <th>Academic Year</th>
                                    <th>Reminder Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $stud): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="student_ids[]" value="<?= $stud['id'] ?>" class="stud-check" checked>
                                        </td>
                                        <td class="fw-bold text-dark"><?= esc($stud['student_name']) ?></td>
                                        <td><span class="badge badge-glass-indigo"><?= esc($stud['eligibility_case_no']) ?></span></td>
                                        <td class="small text-muted"><?= esc($stud['clg_add']) ?></td>
                                        <td><?= esc($stud['admission_taken_year']) ?></td>
                                        <td>
                                            <?php if (!empty($stud['reminder_note_count'])): ?>
                                                <span class="badge badge-glass-amber"><?= (int) $stud['reminder_note_count'] ?> prior notice(s)</span>
                                            <?php else: ?>
                                                <span class="text-muted small">None yet</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-emerald py-2 px-4">
                            <i class="fa fa-file-pdf-o me-1"></i> Generate University Reminder PDF Notice
                        </button>
                    </div>
                </form>

                <?php if (!empty($pager)): ?>
                    <?= $pager->links('default', 'glass') ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fa fa-clock-o fs-1 mb-3 text-secondary"></i>
                    <p class="mb-0">No candidate records match the current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_reminders_js') ?>
<?= $this->endSection() ?>
