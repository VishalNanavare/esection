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
                                        <?php /*
                                          Still a real link to a real page. The
                                          JS intercepts a plain click and opens
                                          the dialog, but ctrl/middle-click and
                                          "open in new tab" are passed through,
                                          and if the script fails to load the
                                          button still goes somewhere.

                                          js-view-conf-batch, NOT the students'
                                          js-view-batch: the two dialogs live in
                                          different JS partials, and sharing the
                                          selector would mean two handlers firing
                                          on one link the moment both files were
                                          ever loaded together.
                                        */ ?>
                                        <a href="<?= base_url('confirmations/batch/' . $b['array_space']) ?>"
                                           class="btn btn-sm btn-glass text-primary me-1 js-view-conf-batch"
                                           data-batch="<?= esc($b['array_space'], 'attr') ?>"
                                           title="View records in this batch">
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

<?php /*
  Batch records, opened from the View button instead of navigating away.
  Filled from confirmations/batch/{n}, which answers an AJAX request with the
  SAME partial its own page renders -- so the dialog cannot show something
  different from the page behind the same URL.

  Nine columns, not ten: no Delete cell. Beyond the request to hide it, the
  delete form declares data-refresh="#confirmation_batch_rows" -- a tbody that
  exists only on the standalone page. Fired from in here it would delete the
  record and then repaint nothing, leaving the row on screen as though it had
  failed. The partial takes showActions=false; the standalone page is unchanged.
*/ ?>
<div class="modal fade" id="confBatchViewModal" tabindex="-1" aria-labelledby="confBatchViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="confBatchViewModalLabel">
                        <i class="fa fa-check-square-o me-2 text-indigo"></i>
                        Confirmation Batch #<span id="conf_view_ref"></span>
                    </h5>
                    <p class="text-muted small mb-0" id="conf_view_meta"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div id="conf_view_loading" class="text-center text-muted py-5">
                    <i class="fa fa-circle-o-notch fa-spin fa-2x d-block mb-2 opacity-50"></i>
                    Loading records...
                </div>

                <div class="table-responsive d-none" id="conf_view_wrap">
                    <table class="table table-glass mb-0">
                        <thead>
                            <tr>
                                <th class="col-sr">#</th>
                                <th>Candidate</th>
                                <th>University</th>
                                <th>Case No.</th>
                                <th>Mig/TC</th>
                                <th>Pass/Dgr</th>
                                <th>Statement of Marks</th>
                                <th>Letter No./Dated</th>
                                <th>Remark</th>
                            </tr>
                        </thead>
                        <tbody id="conf_view_rows"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-top border-secondary border-opacity-25">
                <?php if (can('confirmations.print')): ?>
                    <a href="#" target="_blank" class="btn btn-emerald" id="conf_view_pdf">
                        <i class="fa fa-file-pdf-o me-1"></i> Eligibility Letter
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_confirmations_history_js') ?>
<?= $this->endSection() ?>
