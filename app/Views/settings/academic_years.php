<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-calendar me-2 text-indigo"></i> Academic Years</h3>
                    <p class="text-muted small mb-0">Add academic years and mark the current one. The current year becomes the default across the system.</p>
                </div>
                <div>
                    <a href="<?= base_url('settings') ?>" class="btn btn-glass me-2"><i class="fa fa-arrow-left me-1"></i> Back</a>
                    <button type="button" class="btn btn-indigo" data-bs-toggle="modal" data-bs-target="#addYearModal">
                        <i class="fa fa-plus me-1"></i> Add Academic Year
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass" id="academic_year_table">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Academic Year</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($years)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No academic years recorded yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sr = 1; foreach ($years as $y): ?>
                                <tr class="year-row" data-id="<?= esc($y['id']) ?>" data-label="<?= esc($y['year_label']) ?>"
                                    data-start="<?= esc($y['start_date']) ?>" data-end="<?= esc($y['end_date']) ?>"
                                    data-current="<?= (int) $y['is_current'] ?>">
                                    <td class="fw-semibold text-muted"><?= $sr++ ?></td>
                                    <td class="fw-bold text-dark"><?= esc($y['year_label']) ?></td>
                                    <td class="small text-muted"><?= esc($y['start_date'] ?: '-') ?></td>
                                    <td class="small text-muted"><?= esc($y['end_date'] ?: '-') ?></td>
                                    <td>
                                        <?php if ((int) $y['is_current'] === 1): ?>
                                            <span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> Current</span>
                                        <?php else: ?>
                                            <span class="badge badge-glass-indigo">Archived</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((int) $y['is_current'] !== 1): ?>
                                            <form action="<?= base_url('settings/academic-years/setCurrent/' . $y['id']) ?>" method="post" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-glass text-emerald me-1" title="Mark as current year">
                                                    <i class="fa fa-check"></i> Set Current
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-glass text-primary me-1 edit-year-btn" data-id="<?= $y['id'] ?>">
                                            <i class="fa fa-edit"></i> Edit
                                        </button>
                                        <?php if (feature_enabled('feature_delete_enabled')): ?>
                                        <form action="<?= base_url('settings/academic-years/delete/' . $y['id']) ?>" method="post" class="d-inline delete-year-form" data-name="<?= esc($y['year_label']) ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-glass text-danger" title="Delete academic year">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Academic Year -->
<div class="modal fade" id="addYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-calendar me-2 text-indigo"></i> Add Academic Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('settings/academic-years/store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Academic Year</label>
                            <input type="text" name="year_label" class="form-control" placeholder="e.g. 2025-2026" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Start Date</label>
                            <input type="text" name="start_date" class="form-control es-datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">End Date</label>
                            <input type="text" name="end_date" class="form-control es-datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-12 form-check mt-2">
                            <input type="checkbox" name="is_current" value="1" class="form-check-input" id="add_is_current">
                            <label class="form-check-label small" for="add_is_current">Mark as the current academic year</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Save Academic Year</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Academic Year -->
<div class="modal fade" id="editYearModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit Academic Year</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_year_form" method="post">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Academic Year</label>
                            <input type="text" name="year_label" id="edit_year_label" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Start Date</label>
                            <input type="text" name="start_date" id="edit_start_date" class="form-control es-datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">End Date</label>
                            <input type="text" name="end_date" id="edit_end_date" class="form-control es-datepicker" autocomplete="off" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-12 form-check mt-2">
                            <input type="checkbox" name="is_current" value="1" class="form-check-input" id="edit_is_current">
                            <label class="form-check-label small" for="edit_is_current">Mark as the current academic year</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Update Academic Year</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_academic_years_js') ?>
<?= $this->endSection() ?>
