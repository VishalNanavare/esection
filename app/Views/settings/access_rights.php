<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-shield me-2 text-indigo"></i> Access Rights</h3>
                    <p class="text-muted small mb-0">
                        Choose a staff account, then grant it only the actions it needs.
                        Administrators are not listed &mdash; they can reach everything by role.
                    </p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>
        </div>
    </div>
</div>

<?php if (empty($users)): ?>
    <div class="row"><div class="col-12">
        <div class="glass-card p-5 text-center text-muted">
            <i class="fa fa-users fs-1 mb-3 text-secondary"></i>
            <p class="mb-0">No staff accounts yet. Create one in Settings &rarr; Users.</p>
        </div>
    </div></div>
<?php else: ?>
    <div class="row g-3">
        <!-- Staff picker -->
        <div class="col-lg-3">
            <div class="glass-card p-3 h-100">
                <h6 class="fw-bold text-dark mb-3"><i class="fa fa-user-circle-o me-2 text-indigo"></i> Staff accounts</h6>
                <div class="list-group list-group-flush" id="staff_list">
                    <?php foreach ($users as $i => $u): ?>
                        <?php
                            // Deliberately NOT Bootstrap's .active. That sets
                            // color:#fff, and combined with the bg-transparent
                            // this list needs, the selected name rendered white
                            // on white -- the first staff account simply vanished.
                            // .is-selected is styled in esection-theme.css.
                        ?>
                        <button type="button"
                                class="list-group-item list-group-item-action border-0 rounded-3 mb-1 px-3 py-2 js-staff-pick <?= $i === 0 ? 'is-selected' : '' ?>"
                                data-user-id="<?= (int) $u['id'] ?>"
                                data-username="<?= esc($u['username'], 'attr') ?>">
                            <span class="fw-semibold d-block"><?= esc($u['username']) ?></span>
                            <?php if (! empty($u['full_name'])): ?>
                                <span class="small text-muted"><?= esc($u['full_name']) ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Permission cards for the selected account -->
        <div class="col-lg-9">
            <div class="glass-card p-4">
                <?php
                    // Rendered for the FIRST staff account; picking another swaps
                    // this region from settings/access-rights/user/{id}. The same
                    // partial serves both, so the two can never diverge.
                    $first = $users[0];
                ?>
                <form action="<?= base_url('settings/access-rights/store') ?>" method="post"
                      id="access_rights_form" data-es-own-handler="1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" id="access_rights_user_id" value="<?= (int) $first['id'] ?>">

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4 pb-3 border-bottom border-secondary border-opacity-10">
                        <div>
                            <span class="text-muted small d-block">Editing permissions for</span>
                            <span class="fw-bold text-dark fs-5" id="access_rights_username"><?= esc($first['username']) ?></span>
                        </div>
                        <button type="submit" class="btn btn-indigo" id="access_rights_submit_btn">
                            <i class="fa fa-check me-1"></i> Save Permissions
                        </button>
                    </div>

                    <div id="access_rights_permissions">
                        <?= view('settings/_permission_groups', ['idPrefix' => 'rights'], ['saveData' => false]) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_permissions_js') ?>
    <?= $this->include('common/ajax_settings_access_rights_js') ?>
<?= $this->endSection() ?>
