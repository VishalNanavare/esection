<?php
/**
 * The <tbody> contents of the candidate-reminder history table.
 *
 * @var array $records rows from StudentReminderService::getAll()
 */
?>
<?php if (empty($records)): ?>
    <tr>
        <td colspan="7" class="text-center text-muted py-4">No candidate reminders recorded yet.</td>
    </tr>
<?php else: ?>
    <?php $sr = 1; foreach ($records as $r): ?>
        <tr>
            <td class="fw-semibold text-muted"><?= $sr++ ?></td>
            <td class="fw-bold text-dark"><?= esc($r['student_name']) ?></td>
            <td><span class="badge badge-glass-indigo"><?= esc($r['eligibility_case_no']) ?></span></td>
            <td><?= esc($r['course_name']) ?></td>
            <td class="small text-muted"><?= esc($r['missing_doc']) ?></td>
            <td class="small text-muted"><?= esc($r['created_at']) ?></td>
            <td class="text-end">
                <?php if (can('reminders_student.print')): ?>
                    <a href="<?= base_url('reminders/student/pdf/' . $r['id']) ?>" target="_blank" class="btn btn-sm btn-glass text-primary me-1" title="View / reprint PDF">
                        <i class="fa fa-file-pdf-o"></i> PDF
                    </a>
                <?php endif; ?>
                <?php if (feature_enabled('feature_delete_enabled') && can('reminders_student.delete')): ?>
                    <form action="<?= base_url('reminders/student/delete/' . $r['id']) ?>"
                          method="post" class="d-inline js-ajax delete-student-rem-form"
                          data-title="Candidate reminders" data-refresh="#student_reminder_rows"
                          data-busy-button="Deleting..."
                          data-confirm-title="Delete this reminder?"
                          data-confirm-text="<?= esc($r['student_name'], 'attr') ?>'s reminder will be permanently removed."
                          data-confirm-button="Yes, delete it">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-glass text-danger" title="Delete">
                            <i class="fa fa-trash"></i> Delete
                        </button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
