<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-check-square-o me-2 text-indigo"></i> Eligibility Confirmation Portal</h3>
                    <p class="text-muted small mb-0">Confirm Migration/TC, Passing/Degree, and Statement of Marks status for verified candidates.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled')): ?>
                        <a href="<?= base_url('confirmations/export?year=' . urlencode($selected_year) . '&stream=' . urlencode($selected_stream)) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('confirmations/history') ?>" class="btn btn-glass"><i class="fa fa-history me-1"></i> Confirmation History</a>
                </div>
            </div>

            <!-- Filter Panel with AJAX Select2 -->
            <form action="<?= base_url('confirmations') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Academic Admission Year (AJAX, optional)</label>
                    <select name="year" class="form-select select2-ajax-academic-year">
                        <?php if ($selected_year): ?>
                            <option value="<?= esc($selected_year) ?>" selected><?= esc($selected_year) ?></option>
                        <?php else: ?>
                            <option value="">-- All Years --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Program / Academic Stream (AJAX, optional)</label>
                    <select name="stream" class="form-select select2-ajax-stream">
                        <?php if ($selected_stream): ?>
                            <option value="<?= esc($selected_stream) ?>" selected><?= esc($selected_stream) ?></option>
                        <?php else: ?>
                            <option value="">-- All Streams --</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-semibold">&nbsp;</label>
                    <button type="submit" class="btn btn-indigo w-100 py-2">
                        <i class="fa fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>

            <?php if (!empty($students)): ?>
                <form action="<?= base_url('confirmations/store') ?>" method="post">
                    <?= csrf_field() ?>

                    <!-- Students Listing Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-glass table-sticky-id">
                            <thead>
                                <tr>
                                    <th class="col-sr"><input type="checkbox" id="check_all_conf"></th>
                                    <th>Candidate</th>
                                    <th>Case No.</th>
                                    <th>Target University</th>
                                    <th style="min-width:110px;">Migration / TC</th>
                                    <th style="min-width:110px;">Pass / Degree</th>
                                    <th style="min-width:110px;">Statement of Marks</th>
                                    <th style="min-width:150px;">Letter No. / Dated</th>
                                    <th style="min-width:200px;">Remark</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <?php
                                        $isConfirmed         = !empty($s['confirmation_id']);
                                        $confirmedArraySpace = (int) ($s['confirmation_array_space'] ?? 0);
                                        // Falls back to the generic history list if array_space is
                                        // somehow missing/0 (legacy row, join miss) -- never emit
                                        // confirmations/batch/0 or an empty segment.
                                        $historyHref = $confirmedArraySpace > 0
                                            ? base_url('confirmations/batch/' . $confirmedArraySpace . '?highlight=' . (int) $s['confirmation_id'])
                                            : base_url('confirmations/history');
                                    ?>
                                    <tr class="conf-row">
                                        <td>
                                            <?php if (!$isConfirmed): ?>
                                                <input type="checkbox" name="student_ids[]" value="<?= $s['id'] ?>" class="conf-check">
                                            <?php else: ?>
                                                <i class="fa fa-check-circle text-emerald"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-bold text-dark">
                                            <?= esc($s['student_name']) ?>
                                            <?php if (!empty($s['student_nee_name']) && $s['student_nee_name'] !== '-'): ?>
                                                <span class="text-muted small d-block fw-normal">Nee Name: <?= esc($s['student_nee_name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge badge-glass-indigo"><?= esc($s['eligibility_case_no']) ?></span></td>
                                        <td class="small text-muted"><?= esc($s['clg_add']) ?></td>
                                        <?php if ($isConfirmed): ?>
                                            <td colspan="5" class="text-center text-muted small">Already confirmed &mdash; see <a href="<?= $historyHref ?>">Confirmation History</a></td>
                                        <?php else: ?>
                                            <td>
                                                <select name="checklist[<?= $s['id'] ?>][mig_tc]" class="form-select form-select-sm mig-tc-select">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="checklist[<?= $s['id'] ?>][p_degree]" class="form-select form-select-sm">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="checklist[<?= $s['id'] ?>][s_marks]" class="form-select form-select-sm">
                                                    <option value="">Select</option>
                                                    <option value="Yes">Yes</option>
                                                    <option value="No">No</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="checklist[<?= $s['id'] ?>][letter_no_date]" class="form-control form-control-sm" placeholder="Letter no., date">
                                            </td>
                                            <td>
                                                <input type="text" name="checklist[<?= $s['id'] ?>][remark]" class="form-control form-control-sm mb-1" placeholder="Remark">
                                                <div class="conf-from-panel border rounded p-2 small" style="display:none;">
                                                    <label class="form-label small mb-1">Confirmation From</label>
                                                    <select name="checklist[<?= $s['id'] ?>][conf_from]" class="form-select form-select-sm mb-1 conf-from-select">
                                                        <option value="">Select</option>
                                                        <?php foreach (\App\Services\ConfirmationService::CONF_FROM_OPTIONS as $opt): ?>
                                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <input type="text" name="checklist[<?= $s['id'] ?>][conf_from_text]" class="form-control form-control-sm mb-1 conf-from-other" placeholder="Confirmation from (specify)" style="display:none;">
                                                    <label class="form-label small mb-1">Student Clarification</label>
                                                    <select name="checklist[<?= $s['id'] ?>][conf_from_select]" class="form-select form-select-sm mb-1">
                                                        <option value="">Select</option>
                                                        <?php foreach (\App\Services\ConfirmationService::CLARIFICATION_OPTIONS as $opt): ?>
                                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label class="form-label small mb-1">Name Change</label>
                                                    <select name="checklist[<?= $s['id'] ?>][etc_data]" class="form-select form-select-sm">
                                                        <option value="">Select</option>
                                                        <?php foreach (\App\Services\ConfirmationService::NAME_CHANGE_OPTIONS as $opt): ?>
                                                            <option value="<?= esc($opt) ?>"><?= esc($opt) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </td>
                                            <td><?= render_status_badge('Pending') ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-emerald py-2 px-4">
                            <i class="fa fa-save me-1"></i> Submit Eligibility Confirmation
                        </button>
                    </div>
                </form>

                <?php if (!empty($pager)): ?>
                    <?= $pager->links('default', 'glass') ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fa fa-info-circle fs-1 mb-3 text-secondary"></i>
                    <p class="mb-0">No student records match the current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_confirmations_js') ?>
<?= $this->endSection() ?>
