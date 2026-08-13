<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-users me-2 text-indigo"></i> Users</h3>
                    <p class="text-muted small mb-0">Create staff accounts, change roles, and activate or deactivate access. Deactivated accounts can no longer log in.</p>
                </div>
                <div>
                    <a href="<?= base_url('settings') ?>" class="btn btn-glass me-2"><i class="fa fa-arrow-left me-1"></i> Back</a>
                    <button type="button" class="btn btn-indigo" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fa fa-plus me-1"></i> Add User
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass" id="user_table">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="user_rows">
                        <?= $this->include('settings/_users_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add User -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-users me-2 text-indigo"></i> Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('settings/users/store') ?>" method="post" class="js-ajax"
                  data-title="Users" data-refresh="#user_rows" data-close-modal="#addUserModal"
                  data-reset-on-success="1" data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body p-0">
                    <div class="row g-0">

                        <!-- Account details -->
                        <div class="col-lg-3 user-modal__aside p-4">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="fa fa-user-circle-o me-2 text-indigo"></i> Account
                            </h6>

                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">Password</label>
                                <input type="password" name="password" class="form-control" minlength="8" maxlength="10" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">Full Name</label>
                                <input type="text" name="full_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="mb-0">
                                <label class="form-label text-secondary small fw-semibold">Role</label>
                                <select name="role" class="form-select js-role-select">
                                    <option value="staff">Staff</option>
                                    <option value="admin">Admin</option>
                                </select>
                                <?php
                                    // Access Rights only ever governs staff.
                                    // Admin bypasses AccessFilter by role, so the
                                    // permission column is hidden for them rather
                                    // than left on screen implying it does something.
                                ?>
                                <div class="form-text small text-muted js-admin-note d-none">
                                    Administrators can reach every page by role, so no permissions are set here.
                                </div>
                            </div>
                        </div>

                        <!-- Permissions -->
                        <div class="col-lg-9 user-modal__perms p-4 access-pages-block">
                            <?php
                                // pages_submitted is a marker whose PRESENCE (not its
                                // value) says "an admin configured this user".
                                // Without it a fully-unticked set is indistinguishable
                                // from a caller that predates the feature, and
                                // UserManagementService would silently apply defaults.
                            ?>
                            <input type="hidden" name="pages_submitted" value="1">

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

                            <?php
                                // view(), NOT $this->include(): include()'s second
                                // argument is OPTIONS, not data, so idPrefix and
                                // showHeader were silently dropped -- both modals
                                // rendered the default prefix and produced DUPLICATE
                                // element ids, which makes a label click toggle the
                                // other modal's checkbox. saveData:false stops the
                                // first call's vars leaking into the second.
                            ?>
                            <?= view('settings/_permission_groups', ['idPrefix' => 'add', 'showHeader' => false], ['saveData' => false]) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 justify-content-between">
                    <span class="small text-muted perm-summary">No permissions selected</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-indigo">Save User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_user_form" method="post" class="js-ajax"
                  data-title="Users" data-refresh="#user_rows" data-close-modal="#editUserModal"
                  data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body p-0">
                    <div class="row g-0">

                        <!-- Account details -->
                        <div class="col-lg-3 user-modal__aside p-4">
                            <h6 class="fw-bold text-dark mb-3">
                                <i class="fa fa-user-circle-o me-2 text-indigo"></i> Account
                            </h6>

                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">Username</label>
                                <input type="text" name="username" id="edit_username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">New Password</label>
                                <input type="password" name="password" id="edit_password" class="form-control" minlength="8" maxlength="10" placeholder="Leave blank to keep current password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">Full Name</label>
                                <input type="text" name="full_name" id="edit_full_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-secondary small fw-semibold">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control">
                            </div>
                            <div class="mb-0">
                                <label class="form-label text-secondary small fw-semibold">Role</label>
                                <select name="role" id="edit_role" class="form-select js-role-select">
                                    <option value="staff">Staff</option>
                                    <option value="admin">Admin</option>
                                </select>
                                <?php
                                    // Access Rights only ever governs staff.
                                    // Admin bypasses AccessFilter by role, so the
                                    // permission column is hidden for them rather
                                    // than left on screen implying it does something.
                                ?>
                                <div class="form-text small text-muted js-admin-note d-none">
                                    Administrators can reach every page by role, so no permissions are set here.
                                </div>
                            </div>
                        </div>

                        <!-- Permissions -->
                        <div class="col-lg-9 user-modal__perms p-4 access-pages-block">
                            <?php
                                // pages_submitted is a marker whose PRESENCE (not its
                                // value) says "an admin configured this user".
                                // Without it a fully-unticked set is indistinguishable
                                // from a caller that predates the feature, and
                                // UserManagementService would silently apply defaults.
                            ?>
                            <input type="hidden" name="pages_submitted" value="1">

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

                            <?= view('settings/_permission_groups', ['idPrefix' => 'edit', 'showHeader' => false], ['saveData' => false]) ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25 justify-content-between">
                    <span class="small text-muted perm-summary">No permissions selected</span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-indigo">Update User</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_permissions_js') ?>
    <?= $this->include('common/ajax_settings_users_js') ?>
<?= $this->endSection() ?>
