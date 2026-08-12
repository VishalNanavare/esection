<?php
/**
 * The <tbody> contents of the Users table.
 *
 * Extracted so the full page render and the AJAX refresh emit IDENTICAL markup
 * from one source. Rebuilding these rows in JavaScript would be the usual way
 * to do this, and the usual way for the two to drift -- the "You" badge or the
 * self-account guard below would silently stop applying after an AJAX update.
 *
 * @var array $users         rows from UserManagementService::getAll()
 * @var int   $currentUserId session()->get('id')
 */
?>
<?php if (empty($users)): ?>
    <tr>
        <td colspan="7" class="text-center text-muted py-4">No users recorded yet.</td>
    </tr>
<?php else: ?>
    <?php $sr = 1; foreach ($users as $u): ?>
        <?php $isSelf = ((int) $u['id'] === (int) $currentUserId); ?>
        <tr class="user-row" data-id="<?= esc($u['id']) ?>" data-username="<?= esc($u['username']) ?>"
            data-email="<?= esc($u['email']) ?>" data-full-name="<?= esc($u['full_name']) ?>" data-role="<?= esc($u['role']) ?>">
            <td class="fw-semibold text-muted"><?= $sr++ ?></td>
            <td class="fw-bold text-dark">
                <?= esc($u['username']) ?>
                <?php if ($isSelf): ?><span class="badge badge-glass-indigo ms-1">You</span><?php endif; ?>
            </td>
            <td class="small text-muted"><?= esc($u['full_name'] ?: '-') ?></td>
            <td class="small text-muted"><?= esc($u['email'] ?: '-') ?></td>
            <td><span class="badge badge-glass-indigo text-uppercase"><?= esc($u['role']) ?></span></td>
            <td><?= render_status_badge((int) $u['is_active'] === 1 ? 'active' : 'inactive') ?></td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-glass text-primary me-1 edit-user-btn" data-id="<?= $u['id'] ?>">
                    <i class="fa fa-edit"></i> Edit
                </button>
                <?php if (! $isSelf): ?>
                    <form action="<?= base_url('settings/users/toggleActive/' . $u['id']) ?>"
                          method="post" class="d-inline js-ajax"
                          data-title="Users" data-refresh="#user_rows" data-busy-button="Working...">
                        <?= csrf_field() ?>
                        <?php if ((int) $u['is_active'] === 1): ?>
                            <button type="submit" class="btn btn-sm btn-glass text-danger" title="Deactivate user">
                                <i class="fa fa-ban"></i> Deactivate
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-sm btn-glass text-emerald" title="Reactivate user">
                                <i class="fa fa-check"></i> Reactivate
                            </button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
<?php endif; ?>
