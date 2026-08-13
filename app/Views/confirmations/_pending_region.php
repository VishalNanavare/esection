<?php
/**
 * The pending-candidates region of confirmations/index.php: the submit form,
 * its table, the pager, AND the empty state.
 *
 * All four together, deliberately. Unlike every other list screen here, this
 * one's empty state sits OUTSIDE the <tbody> -- it is the `else` branch of the
 * same condition that renders the form. Extracting only the table body would
 * mean a filter returning nothing renders an empty table with no explanation.
 *
 * The pager travels with it too, because confirmations/index.php:172 drops the
 * whole <nav> when there is only one page.
 *
 * @var array $students
 * @var \CodeIgniter\Pager\Pager|null $pager
 */
?>
            <?php if (!empty($students)): ?>
                <?php
                    // data-keep-query carries the current ?year/?stream/?page onto
                    // the POST so the server re-renders the SAME slice the operator
                    // was looking at. Confirmations::store() pairs it with
                    // $pager->setPath('confirmations'), without which every pager
                    // link would be rebuilt against the POST-only /store URL.
                ?>
                <?php // confirmations/store requires confirmations.create. Without
                      // this gate a view-only user was shown the check-all box, a
                      // checkbox and three selects per row, letter-no and remark
                      // fields -- a full page of data entry thrown away by a 403 at
                      // submit. $canCreate falls through to the read-only cell
                      // rendering that already exists for confirmed rows. ?>
                <?php $canCreate = can('confirmations.create'); ?>
                <?php if ($canCreate): ?>
                <form action="<?= base_url('confirmations/store') ?>" method="post" class="js-ajax"
                      data-title="Confirmations" data-refresh="#confirmation_pending_region"
                      data-keep-query="1" data-busy-button="Saving..."
                      data-busy-title="Recording the confirmations..."
                      data-busy-text="Writing one record per selected candidate.">
                    <?= csrf_field() ?>
                <?php endif; ?>

                    <!-- Students Listing Table -->
                    <div class="table-responsive mb-4">
                        <table class="table table-glass table-sticky-id">
                            <thead>
                                <tr>
                                    <th class="col-sr"><?php if ($canCreate): ?><input type="checkbox" id="check_all_conf"><?php endif; ?></th>
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
                                            <?php if (!$isConfirmed && $canCreate): ?>
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
                                        <?php elseif (! $canCreate): ?>
                                            <?php // Deliberately NOT the "Already confirmed" cell above: this
                                                  // row is still pending, and labelling it confirmed because the
                                                  // reader lacks a permission would be a plain untruth on screen. ?>
                                            <td colspan="5" class="text-center text-muted small">Awaiting confirmation &mdash; you do not have permission to record confirmations.</td>
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

                    <?php if ($canCreate): ?>
                    <div class="text-end">
                        <button type="submit" class="btn btn-emerald py-2 px-4">
                            <i class="fa fa-save me-1"></i> Submit Eligibility Confirmation
                        </button>
                    </div>
                </form>
                    <?php endif; ?>

                <?php if (!empty($pager)): ?>
                    <?= $pager->links('default', 'glass') ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="fa fa-info-circle fs-1 mb-3 text-secondary"></i>
                    <p class="mb-0">No student records match the current filters.</p>
                </div>
            <?php endif; ?>
