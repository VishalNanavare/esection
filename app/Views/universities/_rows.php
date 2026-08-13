<?php
/**
 * The <tbody> contents of the University master table.
 *
 * Extracted so the full page render and the AJAX refresh emit IDENTICAL markup
 * from one source. The data-state / data-id attributes below are not
 * decoration: the client-side filter in ajax_universities_js.php matches on
 * them, so rebuilding these rows in JavaScript would break the filter the
 * moment a column changed.
 *
 * @var array $colleges rows from UniversityService::getAllColleges()
 */
?>
<?php if (empty($colleges)): ?>
    <tr>
        <td colspan="8" class="text-center text-muted py-4">No universities recorded in database.</td>
    </tr>
<?php else: ?>
    <?php $sr = 1; foreach ($colleges as $c): ?>
        <tr class="uni-row" data-id="<?= esc($c['id']) ?>" data-state="<?= esc($c['States']) ?>" data-name="<?= esc($c['Name']) ?>">
            <td class="fw-semibold text-muted"><?= $sr++ ?></td>
            <td class="fw-bold text-dark fs-6"><?= esc($c['Name']) ?></td>
            <td><span class="badge badge-glass-indigo"><?= esc($c['States']) ?></span></td>
            <td><?= esc($c['head_name'] ?: 'The Controller of Examinations') ?></td>
            <td class="fw-semibold text-emerald"><?= format_currency($c['fees']) ?></td>
            <td class="small text-muted"><?= esc($c['in_favour_of'] ?: '-') ?></td>
            <td><?= render_status_badge((int) ($c['is_active'] ?? 1) === 1 ? 'active' : 'inactive') ?></td>
            <td class="text-end">
                <?php if (can('universities.edit')): ?>
                    <button type="button" class="btn btn-sm btn-glass text-primary me-1 edit-uni-btn" data-id="<?= $c['id'] ?>">
                        <i class="fa fa-edit"></i> Edit
                    </button>
                <?php endif; ?>
                <?php if (can('universities.toggle')): ?>
                <form action="<?= base_url('universities/toggleActive/' . $c['id']) ?>"
                      method="post" class="d-inline js-ajax"
                      data-title="Universities" data-refresh="#university_rows" data-busy-button="Working...">
                    <?= csrf_field() ?>
                    <?php if ((int) ($c['is_active'] ?? 1) === 1): ?>
                        <button type="submit" class="btn btn-sm btn-glass text-danger" title="Deactivate university">
                            <i class="fa fa-ban"></i> Deactivate
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-sm btn-glass text-emerald" title="Reactivate university">
                            <i class="fa fa-check"></i> Reactivate
                        </button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
