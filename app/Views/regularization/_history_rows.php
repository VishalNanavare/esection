<?php
/**
 * The <tbody> contents of the Regularization letter history table.
 *
 * @var array $records rows from RegularizationService::getAll()
 */
?>
<?php if (empty($records)): ?>
    <tr>
        <td colspan="7" class="text-center text-muted py-4">No regularization letters recorded yet.</td>
    </tr>
<?php else: ?>
    <?php $sr = 1; foreach ($records as $r): ?>
        <tr>
            <td class="fw-semibold text-muted"><?= $sr++ ?></td>
            <td class="fw-bold text-dark"><?= esc($r['student_name']) ?></td>
            <td class="small text-muted"><?= esc($r['university_name']) ?></td>
            <td><?= esc($r['admission_taken_in']) ?></td>
            <td><?= esc($r['admission_taken_year']) ?></td>
            <td class="small text-muted"><?= esc($r['created_at']) ?></td>
            <td class="text-end">
                <a href="<?= base_url('regularization/pdf/' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-glass text-primary me-1" title="View / reprint PDF">
                    <i class="fa fa-file-pdf-o"></i> PDF
                </a>
                <button type="button" class="btn btn-sm btn-glass text-primary me-1 edit-reg-btn" data-id="<?= $r['id'] ?>" title="Edit">
                    <i class="fa fa-edit"></i> Edit
                </button>
                <?php if (feature_enabled('feature_delete_enabled')): ?>
                    <form action="<?= base_url('regularization/delete/' . $r['id']) ?>"
                          method="post" class="d-inline js-ajax delete-reg-form"
                          data-title="Regularization" data-refresh="#regularization_rows"
                          data-busy-button="Deleting..."
                          data-confirm-title="Delete this regularization letter?"
                          data-confirm-text="<?= esc($r['student_name'], 'attr') ?>'s letter will be permanently removed. This cannot be undone."
                          data-confirm-button="Yes, delete it">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-glass text-danger" title="Delete permanently">
                            <i class="fa fa-trash"></i> Delete
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
