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
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-users me-2 text-indigo"></i> Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('settings/users/store') ?>" method="post" class="js-ajax"
                  data-title="Users" data-refresh="#user_rows" data-close-modal="#addUserModal"
                  data-reset-on-success="1" data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control" minlength="8" maxlength="10" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Full Name</label>
                            <input type="text" name="full_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Role</label>
                            <select name="role" class="form-select">
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-12 access-pages-block">
                            <label class="form-label text-secondary small fw-semibold">Page Access (staff only &mdash; admin always has every page)</label>
                            <input type="hidden" name="pages_submitted" value="1">
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($accessPages as $page): ?>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="pages[]" value="<?= esc($page['page_key']) ?>" id="add_page_<?= esc($page['page_key']) ?>" checked>
                                        <label class="form-check-label small" for="add_page_<?= esc($page['page_key']) ?>"><?= esc($page['page_label']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_user_form" method="post" class="js-ajax"
                  data-title="Users" data-refresh="#user_rows" data-close-modal="#editUserModal"
                  data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">New Password</label>
                            <input type="password" name="password" id="edit_password" class="form-control" minlength="8" maxlength="10" placeholder="Leave blank to keep current password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Role</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-12 access-pages-block">
                            <label class="form-label text-secondary small fw-semibold">Page Access (staff only &mdash; admin always has every page)</label>
                            <input type="hidden" name="pages_submitted" value="1">
                            <div class="d-flex flex-wrap gap-3">
                                <?php foreach ($accessPages as $page): ?>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input edit-page-checkbox" name="pages[]" value="<?= esc($page['page_key']) ?>" id="edit_page_<?= esc($page['page_key']) ?>">
                                        <label class="form-check-label small" for="edit_page_<?= esc($page['page_key']) ?>"><?= esc($page['page_label']) ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_users_js') ?>
<?= $this->endSection() ?>
