<?php
/**
 * The grouped permission cards.
 *
 * Shared by the Access Rights screen and both User modals so the two can never
 * disagree about which permissions exist -- the previous design kept the
 * catalog in three places and they drifted.
 *
 * Each permission renders as a selectable chip rather than a bare checkbox.
 * A raw `.form-check` row put the next checkbox flush against the previous
 * label ("View[]Create[]Edit"), which is unreadable at 32 permissions; a chip
 * carries its own padding and border, so the hit area is obvious and the
 * checked state is visible at a glance instead of needing a 13px tick.
 *
 * @var array  $permissionGroups from AccessRightsService::getGroupedPermissions()
 * @var string $idPrefix         unique per host (add / edit / rights). The Add
 *                               and Edit modals are both in the DOM at once, so
 *                               duplicate ids would make a label click toggle
 *                               the wrong module's checkbox.
 * @var bool   $showHeader       false when the host supplies its own toolbar
 */
$idPrefix   = $idPrefix ?? 'perm';
$showHeader = $showHeader ?? true;
?>
<div class="permission-groups" data-perm-scope="<?= esc($idPrefix, 'attr') ?>">

    <?php if ($showHeader): ?>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fa fa-lock me-2 text-indigo"></i> Permission
            </h6>
            <div class="d-flex align-items-center gap-2">
                <span class="badge badge-glass-indigo perm-count">0 selected</span>
                <button type="button" class="btn btn-sm btn-glass js-perm-all">Select all</button>
                <button type="button" class="btn btn-sm btn-glass js-perm-none">Clear all</button>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($permissionGroups as $module => $group): ?>
        <div class="permission-card" data-perm-module="<?= esc($module, 'attr') ?>">
            <div class="permission-card__head">
                <span class="badge badge-glass-indigo"><?= esc($group['group_label']) ?></span>
                <button type="button" class="btn btn-sm btn-glass js-perm-group-all"
                        data-perm-module="<?= esc($module, 'attr') ?>">Select all</button>
            </div>

            <div class="permission-chips">
                <?php foreach ($group['actions'] as $permission): ?>
                    <?php $id = $idPrefix . '_' . str_replace('.', '_', $permission['key']); ?>
                    <?php
                        // The input is visually hidden but still focusable and
                        // still named pages[] -- the chip is a <label for>, so
                        // keyboard and screen-reader behaviour is unchanged.
                    ?>
                    <input type="checkbox"
                           class="permission-chip__input js-perm-check<?= $permission['action'] === 'view' ? ' js-perm-view' : '' ?>"
                           name="pages[]"
                           value="<?= esc($permission['key'], 'attr') ?>"
                           id="<?= esc($id, 'attr') ?>"
                           data-perm-module="<?= esc($module, 'attr') ?>"
                           data-perm-action="<?= esc($permission['action'], 'attr') ?>"
                           <?= $permission['granted'] ? 'checked' : '' ?>>
                    <label class="permission-chip" for="<?= esc($id, 'attr') ?>">
                        <i class="fa fa-check permission-chip__tick"></i>
                        <span><?= esc($permission['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
