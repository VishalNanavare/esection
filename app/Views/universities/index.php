<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-university me-2 text-indigo"></i> University Master Directory</h3>
                    <p class="text-muted small mb-0">Manage postal addresses, fees, favoring authority titles, and contact information for nationwide target universities.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_export_enabled')): ?>
                        <a href="<?= base_url('universities/export') ?>" class="btn btn-glass me-1">
                            <i class="fa fa-file-excel-o me-1"></i> Export to Excel
                        </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-indigo" data-bs-toggle="modal" data-bs-target="#addUniversityModal">
                        <i class="fa fa-plus me-1"></i> Add New University
                    </button>
                </div>
            </div>

            <!-- Search & State Filter Bar with AJAX Select2 -->
            <div class="row g-3 mb-4 filter-panel">
                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Filter by State (AJAX)</label>
                    <select id="state_filter" class="form-select select2-ajax-state">
                        <option value="">-- All States --</option>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Search by University Name (AJAX)</label>
                    <select id="university_name_filter" class="form-select select2-ajax-college">
                        <option value="">-- All Universities --</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label text-secondary small fw-semibold">&nbsp;</label>
                    <button type="button" class="btn btn-glass w-100 py-2" id="reset_filters">
                        <i class="fa fa-refresh me-1"></i> Reset Filters
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted" id="filter_count"></small>
                <small class="text-danger d-none" id="no_filter_match">
                    <i class="fa fa-info-circle me-1"></i> No universities match the current filters.
                </small>
            </div>

            <!-- University Master Table -->
            <div class="table-responsive">
                <table class="table table-glass table-sticky-id" id="university_table">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>University Name</th>
                            <th>State</th>
                            <th>Head Title</th>
                            <th>Verification Fees</th>
                            <th>Payment In Favour Of</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="university_rows">
                        <?= $this->include('universities/_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add University -->
<div class="modal fade" id="addUniversityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-university me-2 text-indigo"></i> Add Target University Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= base_url('universities/store') ?>" method="post" class="js-ajax"
                  data-title="Universities" data-refresh="#university_rows" data-close-modal="#addUniversityModal"
                  data-reset-on-success="1" data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-secondary small fw-semibold">University Full Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Shivaji University" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">State</label>
                            <select name="state" class="form-select" required>
                                <option value="">-- Select State --</option>
                                <?= $this->include('common/india_states_options') ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Head Title (To)</label>
                            <input type="text" name="head_name" class="form-control" placeholder="The Controller of Examinations">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Verification Fees Amount (₹)</label>
                            <input type="text" name="fees" class="form-control" placeholder="e.g. 500">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Payment Favoring Title (In Favour Of)</label>
                            <input type="text" name="in_favour_of" class="form-control" placeholder="Finance & Accounts Officer, Shivaji University">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Full Postal Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Vidyanagar, Kolhapur - 416004, Maharashtra"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Email (Optional)</label>
                            <input type="email" name="email_id" class="form-control" placeholder="registrar@university.ac.in">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Mobile No. (Optional)</label>
                            <input type="text" name="mobile_no" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Save University Details</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit University -->
<div class="modal fade" id="editUniversityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-card border-secondary border-opacity-25">
            <div class="modal-header border-bottom border-secondary border-opacity-25">
                <h5 class="modal-title fw-bold text-dark"><i class="fa fa-edit me-2 text-indigo"></i> Edit University Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="edit_university_form" method="post" class="js-ajax"
                  data-title="Universities" data-refresh="#university_rows" data-close-modal="#editUniversityModal"
                  data-busy-button="Saving...">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label text-secondary small fw-semibold">University Full Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small fw-semibold">State</label>
                            <select name="state" id="edit_state" class="form-select" required>
                                <option value="">-- Select State --</option>
                                <?= $this->include('common/india_states_options') ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Head Title (To)</label>
                            <input type="text" name="head_name" id="edit_head_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Verification Fees Amount (₹)</label>
                            <input type="text" name="fees" id="edit_fees" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Payment Favoring Title (In Favour Of)</label>
                            <input type="text" name="in_favour_of" id="edit_in_favour_of" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Full Postal Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Email (Optional)</label>
                            <input type="email" name="email_id" id="edit_email_id" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-secondary small fw-semibold">Mobile No. (Optional)</label>
                            <input type="text" name="mobile_no" id="edit_mobile_no" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-indigo">Update University Details</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_universities_js') ?>
<?= $this->endSection() ?>
