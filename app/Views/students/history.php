<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-history me-2 text-indigo"></i> Verification Batch History</h3>
                    <p class="text-muted small mb-0">Browse, edit, or delete previously submitted candidate batches.</p>
                </div>
                <div>
                    <?php // Toggle stays a master kill switch; the permission scopes it per user. ?>
                    <?php if (feature_enabled('feature_export_enabled') && can('students.export')): ?>
                        <?php /* Carries the current filters, so the export is what the
                                 screen is showing rather than the whole table. */ ?>
                        <a href="<?= base_url('students/history/export?' . http_build_query($filters)) ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <a href="<?= base_url('students/new') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to New Entry Form</a>
                </div>
            </div>

            <?php /* Every filter optional -- blank means no restriction, so the
                     screen still opens on real data rather than an empty prompt
                     to search first. The same query string drives the export,
                     so the file always matches what is on screen. */ ?>
            <form action="<?= base_url('students/history') ?>" method="get" class="row g-3 mb-4 filter-panel" id="batch_history_filters">
                <?php /*
                  The three pickers below read api/batch-*, NOT the master-data
                  endpoints the rest of the app uses (api/academic-years,
                  api/streams). They list the values that actually occur in the
                  verification records, so every option offered is one that
                  matches at least one batch.

                  This is not a preference. getBatchSummaries() matches year and
                  course with HAVING MAX(...) = ?, an exact comparison, and the
                  master course list shares NO value with what batches store --
                  it holds "BCOM" where every record holds "F.Y.B.Com". A course
                  filter fed from api/streams would have offered 30 options and
                  matched nothing on any of them.

                  Each carries an empty <option> as well as its current value:
                  Select2's allowClear has nothing to clear to without one.
                */ ?>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Admission Year</label>
                    <select name="year" class="form-select select2-batch-year">
                        <option value=""></option>
                        <?php if ($filters['year'] !== ''): ?>
                            <option value="<?= esc($filters['year']) ?>" selected><?= esc($filters['year']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">University</label>
                    <?php /*
                      Still matched with LIKE server-side, so the typed-term
                      option this picker offers behaves exactly as the free-text
                      input it replaces: "Mumbai" finds every Mumbai address.
                      The stored value is echoed raw, because that is what the
                      LIKE compares against -- the dropdown's labels are
                      flattened for display only.
                    */ ?>
                    <select name="university" class="form-select select2-batch-university">
                        <option value=""></option>
                        <?php if ($filters['university'] !== ''): ?>
                            <option value="<?= esc($filters['university']) ?>" selected><?= esc(flatten_address($filters['university'])) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Course</label>
                    <?php /*
                      New control. `course` was already read by
                      Students::historyFilters() and already applied by
                      StudentModel::getBatchSummaries() -- the screen simply had
                      no way to set it, so a working filter sat unreachable.
                    */ ?>
                    <select name="course" class="form-select select2-batch-course">
                        <option value=""></option>
                        <?php if ($filters['course'] !== ''): ?>
                            <option value="<?= esc($filters['course']) ?>" selected><?= esc($filters['course']) ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Batch No.</label>
                    <input type="text" name="batch" class="form-control" placeholder="e.g. esection1_47"
                           value="<?= esc($filters['batch']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Candidate Name</label>
                    <input type="text" name="name" class="form-control" placeholder="Any part of the name"
                           value="<?= esc($filters['name']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Created From</label>
                    <input type="text" name="date_from" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($filters['date_from']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-secondary small fw-semibold">Created To</label>
                    <input type="text" name="date_to" class="form-control es-datepicker" autocomplete="off"
                           placeholder="YYYY-MM-DD" value="<?= esc($filters['date_to']) ?>">
                </div>
                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="<?= base_url('students/history') ?>" class="btn btn-glass"><i class="fa fa-refresh me-1"></i> Reset</a>
                    <button type="submit" class="btn btn-indigo px-4"><i class="fa fa-filter me-1"></i> Filter</button>
                </div>
            </form>

            <?php /*
              The batch count used to live here as well. It is in the pager bar
              now -- one place, rendered server-side from the same $pager, so it
              cannot disagree with the range beside it. It used to be maintained
              twice: this line in PHP and a second copy in
              ajax_students_history_js, which meant changing the wording in one
              flipped the text the moment the operator paged.
            */ ?>
            <div class="d-flex justify-content-end align-items-center mb-2">
                <small class="text-muted d-none" id="batch_history_loading">
                    <i class="fa fa-circle-o-notch fa-spin me-1"></i> Loading...
                </small>
            </div>

            <div class="table-responsive position-relative" id="batch_history_wrap">
                <table class="table table-glass align-middle mb-0" id="batch_history_table">
                    <thead>
                        <tr>
                            <th>Batch / Created</th>
                            <th>Target University</th>
                            <th>Course &amp; Year</th>
                            <th class="text-center">Size</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="batch_history_rows">
                        <?= $this->include('students/_history_rows') ?>
                    </tbody>
                </table>
            </div>

            <?php /* Rendered by the same 'glass' template the AJAX reply uses,
                     so a paged view cannot drift from a freshly loaded one. */ ?>
            <div id="batch_history_pager">
                <?php if ($pager !== null): ?>
                    <?= $pager->links('default', 'glass') ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php /*
  Batch candidates, opened from the View button instead of navigating away.
  The body is filled from students/batch/{ref}, which answers an AJAX request
  with the SAME partial its own page renders -- so the dialog cannot show
  something different from the page behind the same URL.

  No Edit or Delete column here: the request was to drop them, and the Edit
  button was inert anyway (nothing in the codebase binds .edit-student-btn, and
  its form carries no action). The partial takes showActions=false, so the
  standalone page keeps whatever it had.
*/ ?>
<div class="modal fade" id="batchViewModal" tabindex="-1" aria-labelledby="batchViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <div>
                    <h5 class="modal-title fw-bold text-dark" id="batchViewModalLabel">
                        <i class="fa fa-users me-2 text-indigo"></i>
                        Batch <span id="batch_view_ref"></span>
                    </h5>
                    <p class="text-muted small mb-0" id="batch_view_meta"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div id="batch_view_loading" class="text-center text-muted py-5">
                    <i class="fa fa-circle-o-notch fa-spin fa-2x d-block mb-2 opacity-50"></i>
                    Loading candidates...
                </div>

                <div class="table-responsive d-none" id="batch_view_wrap">
                    <table class="table table-glass mb-0">
                        <thead>
                            <tr>
                                <th class="col-sr">#</th>
                                <th>Candidate</th>
                                <th>Nee Name</th>
                                <th>Case No.</th>
                                <th>Verification Remark</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody id="batch_view_rows"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer border-top border-secondary border-opacity-25">
                <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_students_history_js') ?>
<?= $this->endSection() ?>

