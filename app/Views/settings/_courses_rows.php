<?php
/**
 * The <tbody> contents of the Courses table.
 *
 * Extracted so the full page render and the AJAX refresh emit IDENTICAL markup
 * from one source -- see settings/_users_rows.php for the reasoning.
 *
 * @var array $courses rows from CourseService::getAll()
 */
?>
<?php if (empty($courses)): ?>
    <tr>
        <td colspan="5" class="text-center text-muted py-4">No courses recorded yet.</td>
    </tr>
<?php else: ?>
    <?php $sr = 1; foreach ($courses as $c): ?>
        <tr class="course-row" data-id="<?= esc($c['id']) ?>" data-name="<?= esc($c['name']) ?>" data-code="<?= esc($c['code']) ?>">
            <td class="fw-semibold text-muted"><?= $sr++ ?></td>
            <td class="fw-bold text-dark"><?= esc($c['name']) ?></td>
            <td class="small text-muted"><?= esc($c['code'] ?: '-') ?></td>
            <td>
                <?php if ((int) $c['is_active'] === 1): ?>
                    <span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> Active</span>
                <?php else: ?>
                    <span class="badge badge-glass-amber">Inactive</span>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-glass text-primary me-1 edit-course-btn" data-id="<?= $c['id'] ?>">
                    <i class="fa fa-edit"></i> Edit
                </button>
                <form action="<?= base_url('settings/courses/toggleActive/' . $c['id']) ?>"
                      method="post" class="d-inline js-ajax"
                      data-title="Courses" data-refresh="#course_rows" data-busy-button="Working...">
                    <?= csrf_field() ?>
                    <?php if ((int) $c['is_active'] === 1): ?>
                        <button type="submit" class="btn btn-sm btn-glass text-danger" title="Deactivate course">
                            <i class="fa fa-ban"></i> Deactivate
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-sm btn-glass text-emerald" title="Reactivate course">
                            <i class="fa fa-check"></i> Reactivate
                        </button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
