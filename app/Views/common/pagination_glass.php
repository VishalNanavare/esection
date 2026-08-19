<?php
/**
 * @var \CodeIgniter\Pager\PagerRenderer $pager
 *
 * The app's only pager template, registered as the 'glass' alias in
 * Config\Pager and rendered by every paginated screen as
 * $pager->links('default', 'glass').
 *
 * CI4's shipped default_full.php emits bare <li><a> without Bootstrap 5's
 * required .page-item/.page-link classes, so it looks broken under this app's
 * actual Bootstrap build. This mirrors the rest of the app's "glass" language.
 *
 * It renders a BAR, not just the page links: the row range on the left, the
 * links on the right.
 *
 * It also renders unconditionally. The previous version began
 * `if ($pager->getPageCount() > 1)` and emitted an empty string otherwise --
 * correct while it was only page links, but the count has to survive exactly
 * the case that hid: a result small enough to fit one page is still a result
 * worth counting, and "0 records" is the most useful thing the screen can say.
 *
 * Two screens (confirmations/index, reminders/university) render this inside an
 * `if (!empty($students))` branch of their own, so on an empty result they show
 * their empty-state block instead and never reach this file. That is their
 * choice to make, not this template's.
 */
$pager->setSurroundCount(2);

$pageCount = $pager->getPageCount();
$total     = $pager->getTotal();
$start     = $pager->getPerPageStart();
$end       = $pager->getPerPageEnd();

// getTotal() is null when a pager was built without one; say nothing rather
// than "of 0", which would be a claim we cannot make.
$hasTotal = $total !== null;
?>
<div class="es-pagebar">
    <div class="es-pagebar__count small text-muted">
        <?php if (! $hasTotal): ?>
            <?php /* nothing to state */ ?>
        <?php elseif ($total === 0): ?>
            No records
        <?php elseif ($pageCount <= 1): ?>
            <?= number_format($total) ?> record<?= $total === 1 ? '' : 's' ?>
        <?php else: ?>
            Showing <?= number_format((int) $start) ?>&ndash;<?= number_format((int) $end) ?>
            of <?= number_format($total) ?>
        <?php endif; ?>
    </div>

    <?php if ($pageCount > 1): ?>
        <nav aria-label="Page navigation" class="es-pagebar__nav">
            <ul class="pagination pagination-glass mb-0">
                <?php /*
                  First and Last are separate controls from Prev and Next, and
                  they carry their page NUMBER as the accessible name -- "Go to
                  page 1" / "Go to page 197" says more than "First"/"Last" to
                  someone who cannot see how long the list is.
                */ ?>
                <li class="page-item <?= $pager->hasPrevious() ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $pager->hasPrevious() ? esc($pager->getFirst()) : '#' ?>"
                       aria-label="Go to first page" title="First page">&laquo;&laquo;</a>
                </li>
                <li class="page-item <?= $pager->hasPrevious() ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $pager->hasPrevious() ? esc($pager->getPrevious()) : '#' ?>"
                       aria-label="Go to previous page" title="Previous page">&laquo;</a>
                </li>

                <?php foreach ($pager->links() as $link): ?>
                    <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= esc($link['uri']) ?>"><?= esc($link['title']) ?></a>
                    </li>
                <?php endforeach; ?>

                <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $pager->hasNext() ? esc($pager->getNext()) : '#' ?>"
                       aria-label="Go to next page" title="Next page">&raquo;</a>
                </li>
                <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
                    <a class="page-link" href="<?= $pager->hasNext() ? esc($pager->getLast()) : '#' ?>"
                       aria-label="Go to last page" title="Last page">&raquo;&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
