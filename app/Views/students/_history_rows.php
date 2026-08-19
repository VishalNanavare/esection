<?php
/**
 * One page of dispatch batches.
 *
 * A partial rather than inline markup, because the AJAX pager and the filter
 * both re-render exactly this and nothing else. Sharing it is what stops a
 * paged view drifting from a freshly loaded one.
 *
 * @var array $batches
 * @var array $filters
 */
?>
<?php if (empty($batches)): ?>
    <tr>
        <td colspan="6" class="text-center text-muted py-5">
            <?php
            // "Nothing here" and "nothing matches" are different problems for
            // whoever reads this -- telling someone the register is empty when
            // they have simply filtered too far sends them hunting a data fault
            // that does not exist.
            $isFiltered = false;

            foreach ($filters ?? [] as $value) {
                if ((string) $value !== '') {
                    $isFiltered = true;
                    break;
                }
            }
            ?>
            <?php if ($isFiltered): ?>
                <i class="fa fa-filter fa-2x d-block mb-2 opacity-25"></i>
                No batch matches the current filters.
                <div class="small mt-1">
                    <a href="<?= base_url('students/history') ?>">Clear the filters</a> to see every batch.
                </div>
            <?php else: ?>
                <i class="fa fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                No batches submitted yet.
            <?php endif; ?>
        </td>
    </tr>
<?php else: ?>
    <?php foreach ($batches as $b): ?>
        <tr>
            <?php // Batch and its creation time together: the reference means
                  // little without knowing when it was raised, and pairing them
                  // frees a whole column. ?>
            <td>
                <span class="fw-bold text-dark d-block"><?= esc($b['array_space']) ?></span>
                <span class="small text-muted">
                    <i class="fa fa-clock-o me-1"></i>
                    <?= esc(is_numeric($b['en_time']) ? date('d M Y, H:i', (int) $b['en_time']) : $b['en_time']) ?>
                </span>
            </td>

            <td>
                <span class="d-block"><?= esc_address($b['clg_add']) ?></span>
            </td>

            <?php // Course and year are one fact about the batch, not two. ?>
            <td>
                <span class="d-block"><?= esc($b['admission_taken_in']) ?></span>
                <span class="badge badge-glass-indigo mt-1"><?= esc($b['admission_taken_year']) ?></span>
            </td>

            <td class="text-center">
                <span class="badge badge-glass-emerald">
                    <?= (int) $b['student_count'] ?>
                </span>
                <span class="d-block small text-muted mt-1">
                    <?= (int) $b['student_count'] === 1 ? 'candidate' : 'candidates' ?>
                </span>
            </td>

            <td class="text-end text-nowrap">
                <?php // One group rather than three stacked buttons: the old
                      // layout wrapped into three rows on every line and made
                      // each row three times taller than its content. ?>
                <div class="btn-group btn-group-sm" role="group" aria-label="Batch actions">
                    <?php /*
                      Still a real link to a real page, and deliberately so. The
                      JS below intercepts the click and opens a dialog instead,
                      but middle-click, ctrl-click and "open in new tab" keep
                      working, and if the script fails to load the button still
                      goes somewhere -- which a <button> could not.
                    */ ?>
                    <a href="<?= base_url('students/batch/' . urlencode($b['array_space'])) ?>"
                       class="btn btn-glass text-primary js-view-batch"
                       data-batch="<?= esc($b['array_space'], 'attr') ?>"
                       title="View candidates in this batch">
                        <i class="fa fa-eye"></i><span class="d-none d-xl-inline ms-1">View</span>
                    </a>

                    <?php // Both routes require students.print. They open in a new
                          // tab, so AccessFilter takes its non-JSON branch and the
                          // tab shows the dashboard with an error -- which reads as
                          // a broken link rather than a withheld one. ?>
                    <?php if (can('students.print')): ?>
                        <a href="<?= base_url('pdf/dispatch/' . urlencode($b['array_space'])) ?>" target="_blank"
                           class="btn btn-glass text-emerald" title="Dispatch letter (PDF)">
                            <i class="fa fa-file-pdf-o"></i><span class="d-none d-xl-inline ms-1">PDF</span>
                        </a>
                        <a href="<?= base_url('pdf/dispatchAccounts/' . urlencode($b['array_space'])) ?>" target="_blank"
                           class="btn btn-glass text-emerald" title="Accounts copy (PDF)">
                            <i class="fa fa-file-text-o"></i><span class="d-none d-xl-inline ms-1">AC</span>
                        </a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
