<?php
/**
 * The <tbody> contents of the Confirmation batch detail table.
 *
 * Takes THREE variables. $highlightId drives the `confirmation-row-highlight`
 * class for the "Already confirmed" deep link on confirmations/index.php --
 * omitting it from a render call would silently drop that feature rather than
 * fail, so all three are documented here as the contract.
 *
 * @var array $records     rows from ConfirmationService::getBatchDetail()
 * @var int   $highlightId conf_stud_data.id from ?highlight=, or 0
 * @var bool  $showActions render the Delete cell. Defaults TRUE so the
 *                         standalone page is unchanged; the View dialog on
 *                         confirmations/history passes false.
 */
$highlightId = $highlightId ?? 0;
$showActions = $showActions ?? true;

// Was hardcoded to 10. It has to follow $showActions now, or the "no records"
// row spans a column the header does not have.
$columns = $showActions ? 10 : 9;
?>
<?php if (empty($records)): ?>
    <tr>
        <td colspan="<?= $columns ?>" class="text-center text-muted py-4">No records found for this batch.</td>
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
            <td class="small text-muted"><?= esc_address($r['clg_add'] ?? $r['uni_add'] ?? '') ?></td>
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
            <?php if ($showActions): ?>
            <td class="text-end">
                <?php /*
                  This form declares data-refresh="#confirmation_batch_rows",
                  a tbody that exists ONLY on the standalone batch detail page.
                  Rendered into the dialog on confirmations/history it would
                  POST, delete the record, and then swap nothing -- the row
                  would stay on screen looking undeleted. Keeping the cell out
                  of the dialog is what makes that unreachable.
                */ ?>
                <?php if (feature_enabled('feature_delete_enabled') && can('confirmations.delete')): ?>
                    <form action="<?= base_url('confirmations/delete/' . $r['id']) ?>"
                          method="post" class="d-inline js-ajax delete-confirmation-form"
                          data-title="Confirmations" data-refresh="#confirmation_batch_rows"
                          data-busy-button="Deleting..."
                          data-confirm-title="Delete this confirmation record?"
                          data-confirm-text="<?= esc($r['student_name'] ?? 'This record', 'attr') ?> will be permanently removed from this batch."
                          data-confirm-button="Yes, delete it">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-glass text-danger" title="Delete this confirmation record">
                            <i class="fa fa-trash"></i> Delete
                        </button>
                    </form>
                <?php endif; ?>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
