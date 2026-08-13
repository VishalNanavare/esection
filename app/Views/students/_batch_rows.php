<?php
/**
 * The <tbody> contents of the candidates table on a batch detail page.
 *
 * Extracted so the full page render and the AJAX refresh emit IDENTICAL markup
 * from one source.
 *
 * @var array $students rows for this batch
 */
?>
<?php if (empty($students)): ?>
    <tr>
        <td colspan="7" class="text-center text-muted py-4">No candidates in this batch.</td>
    </tr>
<?php else: ?>
    <?php $i = 1; foreach ($students as $s): ?>
        <tr>
            <td class="fw-semibold text-muted"><?= $i++ ?></td>
            <td class="fw-bold text-dark"><?= esc($s['student_name']) ?></td>
            <td><?= esc($s['student_nee_name']) ?></td>
            <td><span class="badge badge-glass-indigo"><?= esc($s['eligibility_case_no']) ?></span></td>
            <td class="small text-muted"><?= esc($s['verification_of_marksheet_done_by_you']) ?></td>
            <td class="small text-muted"><?= esc($s['email'] ?: '-') ?></td>
            <td class="text-end">
                <?php if (can('students.edit')): ?>
                    <button type="button" class="btn btn-sm btn-glass text-primary me-1 edit-student-btn" data-id="<?= $s['id'] ?>">
                        <i class="fa fa-edit"></i> Edit
                    </button>
                <?php endif; ?>
                <?php if (feature_enabled('feature_delete_enabled') && can('students.delete')): ?>
                    <?php
                        // The confirmation is declared here, not bound in JS. The
                        // old handler ended in form.submit(), which fires no
                        // submit event at all -- see the note in
                        // common/ajax_students_history_js.php.
                    ?>
                    <form action="<?= base_url('students/delete/' . $s['id']) ?>"
                          method="post" class="d-inline js-ajax delete-student-form"
                          data-title="Candidates" data-refresh="#batch_student_rows"
                          data-busy-button="Deleting..."
                          data-confirm-title="Delete this candidate permanently?"
                          data-confirm-text="<?= esc($s['student_name'], 'attr') ?> will be permanently removed. This cannot be undone."
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
