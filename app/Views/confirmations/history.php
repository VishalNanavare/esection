<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> Confirmation History</h3>
                    <p class="text-muted small mb-0">Browse previously confirmed eligibility batches.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled') && can('confirmations.export')): ?>
                        <?php /* http_build_query rather than hand-concatenation: the filter list has
         grown twice now, and every addition had to be remembered here too or
         the export silently stopped matching the screen. */ ?>
                        <a href="<?= base_url('confirmations/history/export?' . http_build_query([
                            'year'         => $selected_year,
                            'stream'       => $selected_stream,
                            'clg_add'      => $selected_colg,
                            'student_name' => $selected_name,
                            'date_from'    => $selected_date_from,
                            'date_to'      => $selected_date_to,
                        ])) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-glass me-1" data-reload><i class="fa fa-refresh me-1"></i> Refresh</button>
                    <a href="<?= base_url('confirmations') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Confirmation Portal</a>
                </div>
            </div>

            <!-- Filter Panel with AJAX Select2 -->
            <form action="<?= base_url('confirmations/history') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Academic Admission Year (AJAX, optional)</label>
                    <select name="year" class="form-select select2-ajax-academic-year">
                        <?php if ($selected_year): ?>
                            <option value="<?= esc($selected_year) ?>" selected><?= esc($selected_year) ?></option>
                        <?php else: ?>
                            <option value="">-- All Years --</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Program / Academic Stream (AJAX, optional)</label>
                    <select name="stream" class="form-select select2-ajax-stream">
                        <?php if ($selected_stream): ?>
                            <option value="<?= esc($selected_stream) ?>" selected><?= esc($selected_stream) ?></option>
                        <?php else: ?>
                            <option value="">-- All Streams --</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Target University (AJAX, optional)</label>
                    <select name="clg_add" class="form-select select2-ajax-college">
                        <?php if ($selected_colg): ?>
                            <option value="<?= esc($selected_colg) ?>" selected><?= esc($selected_colg) ?></option>
                        <?php else: ?>
                            <option value="">-- All Universities --</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Student Name (optional)</label>
                    <input type="text" name="student_name" class="form-control" placeholder="e.g. Farah Naaz" value="<?= esc($selected_name) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Confirmed From</label>
                    <input type="text" name="date_from" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($selected_date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Confirmed To</label>
                    <input type="text" name="date_to" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($selected_date_to) ?>">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="<?= base_url('confirmations/history') ?>" class="btn btn-glass"><i class="fa fa-refresh me-1"></i> Reset</a>
                    <button type="submit" class="btn btn-indigo px-4"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Confirmed On</th>
                            <th>Confirmed By</th>
                            <th class="text-center">Candidates</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($batches)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <?= ($selected_year || $selected_stream || $selected_colg || $selected_name)
                                        ? 'No confirmed batches match the current filters.'
                                        : 'No confirmed batches yet.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($batches as $b): ?>
                                <tr>
                                    <td class="fw-bold text-dark">#<?= esc($b['array_space']) ?></td>
                                    <td class="small text-muted"><?= esc(is_numeric($b['en_time']) ? date('d/m/Y H:i', (int) $b['en_time']) : $b['en_time']) ?></td>
                                    <td class="small text-muted"><?= esc($b['en_by'] ?: '-') ?></td>
                                    <td class="text-center"><span class="badge badge-glass-indigo"><?= (int) $b['student_count'] ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= base_url('confirmations/batch/' . $b['array_space']) ?>" class="btn btn-sm btn-glass text-primary me-1">
                                            <i class="fa fa-eye"></i> View
                                        </a>
                                        <?php if (can('confirmations.print')): ?>
                                            <a href="<?= base_url('confirmations/eligibilityPdf/' . $b['array_space']) ?>" target="_blank" class="btn btn-sm btn-glass text-emerald">
                                                <i class="fa fa-file-pdf-o"></i> Eligibility Letter
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($pager)): ?>
                <?= $pager->links('default', 'glass') ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_confirmations_history_js') ?>
<?= $this->endSection() ?>
