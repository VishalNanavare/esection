<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-check-square-o me-2 text-indigo"></i> Demand Draft (DD) Payment Confirmation Portal</h3>
                    <p class="text-muted small mb-0">Record DD numbers, bank names, and payment dates for verified candidate eligibility batches.</p>
                </div>
            </div>

            <!-- Filter Panel with AJAX Select2 -->
            <form action="<?= base_url('confirmations') ?>" method="get" class="row g-3 mb-4 p-3 rounded-3" style="background: #f8fafc; border: 1px solid var(--border-card);">
                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Academic Admission Year</label>
                    <select name="year" class="form-select" required>
                        <option value="">-- Select Academic Year --</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= esc($y['admission_taken_year']) ?>" <?= $selected_year === $y['admission_taken_year'] ? 'selected' : '' ?>>
                                <?= esc($y['admission_taken_year']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Program / Academic Stream (AJAX)</label>
                    <select name="stream" class="form-select select2-ajax-stream" required>
                        <?php if ($selected_stream): ?>
                            <option value="<?= esc($selected_stream) ?>" selected><?= esc($selected_stream) ?></option>
                        <?php else: ?>
                            <option value="">-- Type / Select Program Stream --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-indigo w-100 py-2">
                        <i class="fa fa-filter me-1"></i> Load Students
                    </button>
                </div>
            </form>

            <?php if (!empty($students)): ?>
                <form action="<?= base_url('confirmations/store') ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- Payment Information Bar -->
                    <div class="row g-3 mb-4 p-3 rounded-3" style="background: #ffffff; border: 1px dashed #cbd5e1;">
                        <div class="col-md-3">
                            <label class="form-label text-secondary small fw-semibold">Demand Draft (DD) No.</label>
                            <input type="text" name="dd_no" class="form-control" placeholder="e.g. 562814" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small fw-semibold">Issuing Bank Name</label>
                            <input type="text" name="bank_name" class="form-control" placeholder="e.g. State Bank of India" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small fw-semibold">DD Issue Date</label>
                            <input type="date" name="dd_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-secondary small fw-semibold">DD Total Amount (₹)</label>
                            <input type="text" name="dd_amount" class="form-control" placeholder="e.g. 1500" required>
                        </div>
                    </div>

                    <!-- Students Listing Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-glass">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="check_all_conf"></th>
                                    <th>Candidate Name</th>
                                    <th>Maiden Name</th>
                                    <th>Eligibility Case No.</th>
                                    <th>Target University</th>
                                    <th>DD Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td>
                                            <?php if (empty($s['dd_no'])): ?>
                                                <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="conf-check">
                                            <?php else: ?>
                                                <i class="fa fa-check-circle text-emerald"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-dark"><?= esc($s['student_name']) ?></td>
                                        <td><?= esc($s['student_nee_name']) ?></td>
                                        <td><span class="badge badge-glass-indigo"><?= esc($s['eligibility_case_no']) ?></span></td>
                                        <td class="small text-muted"><?= esc($s['clg_add']) ?></td>
                                        <td>
                                            <?php if (!empty($s['dd_no'])): ?>
                                                <?= render_status_badge('Confirmed') ?>
                                                <span class="small text-muted d-block mt-1">DD: <?= esc($s['dd_no']) ?> (<?= format_currency($s['dd_amount']) ?>)</span>
                                            <?php else: ?>
                                                <?= render_status_badge('Pending') ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-emerald py-2 px-4">
                            <i class="fa fa-save me-1"></i> Submit DD Payment Confirmation
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fa fa-info-circle fs-1 mb-3 text-secondary"></i>
                    <p class="mb-0">Select Academic Year and Program Stream above to display student records for payment confirmation.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_confirmations_js') ?>
<?= $this->endSection() ?>
