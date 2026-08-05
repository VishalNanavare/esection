<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-check-square-o me-2 text-indigo"></i> Confirmation Batch #<?= esc($arraySpace) ?></h3>
                    <p class="text-muted small mb-0">Full eligibility confirmation detail for this batch.</p>
                </div>
                <div>
                    <a href="<?= base_url('confirmations/eligibilityPdf/' . $arraySpace) ?>" target="_blank" class="btn btn-emerald me-1"><i class="fa fa-file-pdf-o me-1"></i> Eligibility Letter</a>
                    <a href="<?= base_url('confirmations/history') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to History</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Candidate</th>
                            <th>University</th>
                            <th>Case No.</th>
                            <th>Mig/TC</th>
                            <th>Pass/Dgr</th>
                            <th>Statement of Marks</th>
                            <th>Letter No./Dated</th>
                            <th>Remark</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No records found for this batch.</td>
                            </tr>
                        <?php else: ?>
                            <?php $i = 1; foreach ($records as $r): ?>
                                <?php $isHighlighted = !empty($highlightId) && (int) $r['id'] === (int) $highlightId; ?>
                                <tr id="conf-record-<?= esc($r['id']) ?>" class="<?= $isHighlighted ? 'confirmation-row-highlight' : '' ?>">
                                    <td class="fw-semibold text-muted"><?= $i++ ?></td>
                                    <td class="fw-bold text-dark">
                                        <?= esc($r['student_name'] ?? '') ?>
                                        <?php if (!empty($r['student_nee_name']) && $r['student_nee_name'] !== '-'): ?>
                                            <span class="text-muted small d-block fw-normal">Nee Name: <?= esc($r['student_nee_name']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted"><?= esc($r['clg_add'] ?? $r['uni_add'] ?? '') ?></td>
                                    <td><span class="badge badge-glass-indigo"><?= esc($r['case_no']) ?></span></td>
                                    <td><?= esc($r['mig_TC'] ?: '-') ?></td>
                                    <td><?= esc($r['p_degree'] ?: '-') ?></td>
                                    <td><?= esc($r['s_marks'] ?: '-') ?></td>
                                    <td class="small"><?= esc($r['letter_no_date'] ?: '-') ?></td>
                                    <td class="small">
                                        <?= esc($r['remark'] ?: '-') ?>
                                        <?php if (!empty($r['conf_from'])): ?>
                                            <span class="text-muted d-block">Conf. From: <?= esc($r['conf_from'] === 'other' ? $r['conf_from_text'] : $r['conf_from']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['conf_from_select'])): ?>
                                            <span class="text-muted d-block">Clarification: <?= esc($r['conf_from_select']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['etc_data'])): ?>
                                            <span class="text-muted d-block">Name Change: <?= esc($r['etc_data']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (feature_enabled('feature_delete_enabled')): ?>
                                        <form action="<?= base_url('confirmations/delete/' . $r['id']) ?>" method="post" class="d-inline delete-confirmation-form" data-name="<?= esc($r['student_name'] ?? 'This record') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-glass text-danger" title="Delete this confirmation record">
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
    <?= $this->include('common/ajax_confirmations_history_js') ?>
<?= $this->endSection() ?>
