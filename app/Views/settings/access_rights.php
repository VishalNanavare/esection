<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-lock me-2 text-indigo"></i> Access Rights</h3>
                    <p class="text-muted small mb-0">Choose which pages each staff account can use. Admin accounts always have access to everything.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>

            <?php if (empty($users)): ?>
                <p class="text-center text-muted py-4">No staff accounts yet.</p>
            <?php else: ?>
                <form id="access_rights_form" action="<?= base_url('settings/access-rights/store') ?>" method="post" data-es-own-handler="1">
                    <?= csrf_field() ?>
                    <div class="table-responsive">
                        <table class="table table-glass align-middle">
                            <thead>
                                <tr>
                                    <th>Staff User</th>
                                    <?php foreach ($pages as $page): ?>
                                        <th class="text-center"><?= esc($page['page_label']) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            <?= esc($u['username']) ?>
                                            <?php if (!empty($u['full_name'])): ?>
                                                <span class="text-muted small d-block fw-normal"><?= esc($u['full_name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <?php foreach ($pages as $page): ?>
                                            <td class="text-center">
                                                <input type="checkbox"
                                                       name="grants[<?= (int) $u['id'] ?>][]"
                                                       value="<?= esc($page['page_key']) ?>"
                                                       <?= !empty($u['pages'][$page['page_key']]) ? 'checked' : '' ?>>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-indigo" id="access_rights_submit_btn"><i class="fa fa-check me-1"></i> Save Access Rights</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_access_rights_js') ?>
<?= $this->endSection() ?>
