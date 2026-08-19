<?php
/**
 * The <tbody> contents of the Academic Years table.
 *
 * Extracted so the full page render and the AJAX refresh emit IDENTICAL markup
 * from one source. This screen is the strongest case for it: setCurrent changes
 * TWO rows (the new current one and the old one) and also changes whether the
 * "Set Current" button is rendered at all, so patching a single cell in
 * JavaScript would leave the previous current year still showing its badge.
 *
 * @var array $years rows from AcademicYearService::getAll()
 */
?>
<?php if (empty($years)): ?>
    <tr>
        <td colspan="4" class="text-center text-muted py-4">No academic years recorded yet.</td>
    </tr>
<?php else: ?>
    <?php $sr = 1; foreach ($years as $y): ?>
        <?php
            // How many records name this year. getAll(true) supplies it; the
            // ?? 0 keeps this partial renderable from a caller that did not ask
            // for usage rather than fatalling on a missing key.
            $usage     = (int) ($y['usage_count'] ?? 0);
            $isCurrent = (int) $y['is_current'] === 1;

            // Mirrors AcademicYearService::delete() exactly. The service is the
            // authority -- this only decides what the button looks like, and a
            // POST that gets past it is still refused there.
            $blockedBy = $usage > 0
                ? number_format($usage) . ' record' . ($usage === 1 ? '' : 's') . ' still use this period.'
                : ($isCurrent ? 'This is the current academic year.' : '');
        ?>
        <tr class="year-row" data-id="<?= esc($y['id']) ?>" data-label="<?= esc($y['year_label']) ?>"
            data-current="<?= (int) $y['is_current'] ?>" data-usage="<?= $usage ?>">
            <td class="fw-semibold text-muted"><?= $sr++ ?></td>
            <td class="fw-bold text-dark"><?= esc($y['year_label']) ?></td>
            <td>
                <?php if ((int) $y['is_current'] === 1): ?>
                    <span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> Current</span>
                <?php else: ?>
                    <span class="badge badge-glass-indigo">Archived</span>
                <?php endif; ?>
                <?php if ($usage > 0): ?>
                    <?php /* Shown, not left to the disabled button's tooltip: this
                             number IS the reason the year cannot be removed, and a
                             tooltip is invisible until someone already suspects
                             there is something to hover. */ ?>
                    <span class="text-muted small d-block mt-1">
                        <?= number_format($usage) ?> record<?= $usage === 1 ? '' : 's' ?>
                    </span>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <?php if ((int) $y['is_current'] !== 1): ?>
                    <form action="<?= base_url('settings/academic-years/setCurrent/' . $y['id']) ?>"
                          method="post" class="d-inline js-ajax"
                          data-title="Academic Years" data-refresh="#academic_year_rows" data-busy-button="Working...">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-glass text-emerald me-1" title="Mark as current year">
                            <i class="fa fa-check"></i> Set Current
                        </button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-glass text-primary me-1 edit-year-btn" data-id="<?= $y['id'] ?>">
                    <i class="fa fa-edit"></i> Edit
                </button>
                <?php if (feature_enabled('feature_delete_enabled')): ?>
                    <?php if ($blockedBy !== ''): ?>
                        <?php /*
                          Shown disabled rather than removed. Every table stores
                          the year label as free text, so deleting a year in use
                          orphans those records silently -- the database will not
                          stop it and nothing cascades. Hiding the button would
                          leave an admin looking for a control that used to be
                          there; a disabled one carrying the reason answers the
                          question instead of raising it.
                        */ ?>
                        <button type="button" class="btn btn-sm btn-glass text-muted" disabled
                                title="<?= esc($blockedBy . ' Delete is only available for a period with no data.', 'attr') ?>">
                            <i class="fa fa-trash"></i>
                        </button>
                    <?php else: ?>
                        <?php
                            // The confirmation is declared here rather than bound in
                            // JavaScript. The old handler ended in form.submit(),
                            // which fires no submit event at all -- so a delegated
                            // AJAX handler could never have seen it, and the two
                            // together would have deleted the row while the "Are you
                            // sure?" dialog was still open.
                        ?>
                        <form action="<?= base_url('settings/academic-years/delete/' . $y['id']) ?>"
                              method="post" class="d-inline js-ajax delete-year-form"
                              data-title="Academic Years" data-refresh="#academic_year_rows"
                              data-busy-button="Deleting..."
                              data-confirm-title="Delete this academic year?"
                              data-confirm-text="<?= esc($y['year_label'], 'attr') ?> will be permanently removed. Nothing currently uses it."
                              data-confirm-button="Yes, delete it">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-sm btn-glass text-danger" title="Delete academic year">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
