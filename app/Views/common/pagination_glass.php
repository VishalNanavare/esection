<?php
/**
 * @var \CodeIgniter\Pager\PagerRenderer $pager
 *
 * Custom pager template -- CI4's shipped default_full.php renders bare
 * <li><a> tags without Bootstrap 5's required .page-item/.page-link
 * classes, so it looks broken under this app's actual Bootstrap 5 build.
 * This mirrors the rest of the app's "glass" visual language instead.
 */
$pager->setSurroundCount(2);
?>
<?php if ($pager->getPageCount() > 1): ?>
<nav aria-label="Page navigation" class="d-flex justify-content-center mt-3">
    <ul class="pagination pagination-glass mb-0">
        <li class="page-item <?= $pager->hasPrevious() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->hasPrevious() ? esc($pager->getPrevious()) : '#' ?>" aria-label="Previous">&laquo;</a>
        </li>
        <?php foreach ($pager->links() as $link): ?>
            <li class="page-item <?= $link['active'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= esc($link['uri']) ?>"><?= esc($link['title']) ?></a>
            </li>
        <?php endforeach; ?>
        <li class="page-item <?= $pager->hasNext() ? '' : 'disabled' ?>">
            <a class="page-link" href="<?= $pager->hasNext() ? esc($pager->getNext()) : '#' ?>" aria-label="Next">&raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
