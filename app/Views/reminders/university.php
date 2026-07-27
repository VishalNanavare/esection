<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-clock-o me-2 text-indigo"></i> University Verification Reminder Portal</h3>
                    <p class="text-muted small mb-0">Generate 1st and 2nd reminder notice PDF letters for pending eligibility verifications.</p>
                </div>
                <a href="<?= base_url('reminders/student') ?>" class="btn btn-glass">
                    <i class="fa fa-user me-1"></i> Switch to Candidate Reminders
                </a>
            </div>

            <!-- Search Filter Bar with AJAX Select2 -->
            <form action="<?= base_url('reminders/university') ?>" method="post" class="row g-3 mb-4 p-3 rounded-3" style="background: #f8fafc; border: 1px solid var(--border-card);">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Academic Year</label>
                    <select name="acd_year" class="form-select" required>
                        <option value="">-- Select Year --</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= esc($y['admission_taken_year']) ?>" <?= $selected_year === $y['admission_taken_year'] ? 'selected' : '' ?>>
                                <?= esc($y['admission_taken_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Academic Program / Stream (AJAX)</label>
                    <select name="stream" class="form-select select2-ajax-stream" required>
                        <?php if ($selected_stream): ?>
                            <option value="<?= esc($selected_stream) ?>" selected><?= esc($selected_stream) ?></option>
                        <?php else: ?>
                            <option value="">-- Select Stream --</option>
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
                <form action="<?= base_url('reminders/generateUniversityReminder') ?>" method="post" target="_blank">
                    <?= csrf_field() ?>
                    
                    <div class="row g-3 mb-4 p-3 rounded-3" style="background: #ffffff; border: 1px dashed #cbd5e1;">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Reminder Notice Type</label>
                            <select name="reminder_type" class="form-select" required>
                                <option value="1st Reminder" selected>1st Reminder Notice</option>
                                <option value="2nd Reminder">2nd Reminder Notice</option>
                                <option value="Final Urgent Reminder">Final Urgent Reminder Notice</option>
                            </select>
                        </div>
                    </div>

                    <!-- Student Selection Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-glass">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="check_all"></th>
                                    <th>Candidate Name</th>
                                    <th>Eligibility Case No.</th>
                                    <th>Target University Address</th>
                                    <th>Academic Year</th>
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
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fa fa-clock-o fs-1 mb-3 text-secondary"></i>
                    <p class="mb-0">Select Academic Year & Stream above to display pending student cases for reminder notices.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_reminders_js') ?>
<?= $this->endSection() ?>
