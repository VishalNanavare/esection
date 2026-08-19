<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<?php /*
  Batch-table presentation.

  Screen-local rules only. The row animations (esRowIn / esRowOut / esRowFlash)
  and their reduced-motion guard live in esection-theme.css, so every list in
  the application shares one rhythm rather than each screen inventing its own
  timings.

  An inline style block is fine here: Config\ContentSecurityPolicy allows
  'unsafe-inline' for styleSrc, and none of this belongs in the global sheet.
*/ ?>
<style>
    /* Ten rows, then scroll. Measured against this table's own row height so
       it holds ten whatever the font settings, and the header stays put. */
    #student_batch_scroll {
        max-height: calc(10 * 3.25rem + 3rem);
        overflow-y: auto;
        overflow-x: auto;
    }

    #student_batch_table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--bg-card);
    }

    /* Selected and editing rows read as such without relying on colour alone:
       each carries an inset bar as well as a tint. */
    #student_batch_table tbody tr.es-row-selected {
        background: rgba(99, 102, 241, .08);
        box-shadow: inset 3px 0 0 0 var(--accent-indigo);
        transition: background-color var(--es-dur-base) var(--es-ease);
    }

    #student_batch_table tbody tr.es-row-editing {
        background: rgba(16, 185, 129, .10);
        box-shadow: inset 3px 0 0 0 var(--accent-emerald);
        transition: background-color var(--es-dur-base) var(--es-ease);
    }

    /* Hidden by the filter rather than removed -- keeps its checkbox and its
       selection while the operator narrows the list. */
    #student_batch_table tbody tr.es-row-filtered { display: none; }
</style>


<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1"><i class="fa fa-plus-circle me-2 text-gradient-indigo"></i> New Student Verification Form</h3>
                    <p class="text-muted small mb-0">Create eligibility verification dispatch cases for target universities across India.</p>
                </div>
                <div>
                    <?php if (feature_enabled('feature_import_enabled') && can('students.import')): ?>
                        <a href="<?= base_url('students/import') ?>" class="btn btn-glass me-2"><i class="fa fa-file-excel-o me-1"></i> Import from Excel</a>
                    <?php endif; ?>
                    <?php if (can('students.view')): ?>
                        <a href="<?= base_url('students/history') ?>" class="btn btn-glass me-2"><i class="fa fa-history me-1"></i> Batch History</a>
                    <?php endif; ?>
                    <span class="badge badge-glass-indigo fs-6">Batch #: <span id="display_common_no"><?= esc($common_no) ?></span></span>
                </div>
            </div>

            <!-- University & Program Details with AJAX Select2 -->
            <div class="row g-3 mb-4 filter-panel">
                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">Target University Name (AJAX Search)</label>
                    <select class="form-select select2-ajax-college" id="clg_add_select" required>
                        <option value="" selected>-- Type or Select Target University --</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">To (Addressed Authority Title)</label>
                    <input type="text" class="form-control" id="to" placeholder="e.g. The Controller of Examinations">
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">Academic Admission Year (AJAX)</label>
                    <select class="form-select select2-ajax-academic-year" id="admission_taken_year" required>
                        <option value="" selected>-- Select Academic Year --</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label text-secondary small fw-semibold">Admitted Program / Stream (AJAX)</label>
                    <select class="form-select select2-ajax-stream" id="admission_taken_in" required>
                        <option value="" selected>-- Select Academic Program --</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label text-secondary small fw-semibold">Favoring Authority Payment Instruction (In Favour Of)</label>
                    <input type="text" class="form-control" id="In_Favour_of" placeholder="Auto-populated payment title">
                </div>

                <div class="col-12">
                    <label class="form-label text-secondary small fw-semibold">Full Postal Address</label>
                    <textarea class="form-control" id="clg_add" rows="2" placeholder="University full postal address"></textarea>
                </div>
            </div>

            <!-- Single Candidate Input Entry Panel -->
            <h5 class="fw-bold mb-3"><i class="fa fa-user-plus me-2 text-emerald"></i> Add Candidate to Batch Dispatch</h5>
            <div class="row g-3 mb-4 entry-panel">
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="stud_name" placeholder="Student Full Name">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="stud_nee_name" placeholder="Nee / Maiden Name (Optional)">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="eligibility_case_no" placeholder="Eligibility Case No."
                           value="<?= esc($suggestedCaseNo) ?>" title="Suggested from Settings &gt; Document Numbering -- edit freely">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <input type="text" class="form-control" id="verification_by_you" placeholder="Verification Remarks">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <input type="email" class="form-control" id="stud_email" placeholder="Email (Optional)">
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-glass me-2" id="btn_open_sheet"
                            data-bs-toggle="modal" data-bs-target="#candidateSheetModal">
                        <i class="fa fa-file-excel-o me-1"></i> Fill from Excel
                    </button>
                    <button type="button" class="btn btn-glass text-muted me-2" id="btn_cancel_edit" style="display:none;">
                        <i class="fa fa-times me-1"></i> Cancel Edit
                    </button>
                    <button type="button" class="btn btn-emerald" id="btn_add_student">
                        <i class="fa fa-plus me-1"></i> Add Candidate to List
                    </button>
                </div>
            </div>

            <!-- Candidate Batch Table -->
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-3 flex-grow-1" style="min-width: 16rem; max-width: 30rem;">
                    <div class="es-search">
                        <i class="fa fa-search es-search__icon" aria-hidden="true"></i>
                        <input type="search" class="form-control" id="batch_filter"
                               placeholder="Filter by candidate name" autocomplete="off"
                               aria-label="Filter the batch list by candidate name">
                        <button class="es-search__clear" type="button" id="btn_clear_filter"
                                title="Clear filter" aria-label="Clear filter">
                            <i class="fa fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                    <span class="text-muted small text-nowrap" id="batch_filter_note"></span>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="badge badge-glass-indigo" id="batch_selection_badge" style="display:none;"></span>
                    <button type="button" class="btn btn-sm btn-glass text-primary" id="btn_bulk_edit" disabled>
                        <i class="fa fa-pencil-square-o me-1"></i> Bulk Update
                    </button>
                    <button type="button" class="btn btn-sm btn-glass text-danger" id="btn_bulk_delete" disabled>
                        <i class="fa fa-trash me-1"></i> Remove Selected
                    </button>
                </div>
            </div>

            <div class="table-responsive mb-4" id="student_batch_scroll">
                <table class="table table-glass table-sticky-id mb-0" id="student_batch_table">
                    <thead>
                        <tr>
                            <th style="width:2.5rem;">
                                <input type="checkbox" class="form-check-input" id="batch_check_all"
                                       title="Select all shown" aria-label="Select all shown candidates">
                            </th>
                            <th class="col-sr">#</th>
                            <th>Student Full Name</th>
                            <th>Nee / Maiden Name</th>
                            <th>Eligibility Case No.</th>
                            <th>Verification Remarks</th>
                            <th>Email</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="empty_row">
                            <td colspan="8" class="text-center text-muted py-4">No candidates added to this batch yet. Use the form above to add students.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Action Footer -->
            <div class="d-flex justify-content-between align-items-center pt-3 border-top border-secondary border-opacity-25">
                <a href="<?= base_url('dashboard') ?>" class="btn btn-glass">
                    <i class="fa fa-arrow-left me-1"></i> Back to Dashboard
                </a>

                <button type="button" class="btn btn-indigo" id="btn_save_and_pdf">
                    <i class="fa fa-file-pdf-o me-1"></i> Create PDF & Save Dispatch Case
                </button>
            </div>
        </div>
    </div>
</div>
<!--
  Fill from Excel.

  Reads a five-column sheet and drops the ticked rows into the batch table
  above. It saves nothing by itself -- the operator still picks the university,
  year and course, and the ordinary Save button writes the batch through the
  same path a typed batch uses.
-->
<div class="modal fade" id="candidateSheetModal" tabindex="-1" aria-labelledby="candidateSheetLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="candidateSheetLabel">
                    <i class="fa fa-file-excel-o me-2 text-emerald"></i>Fill Candidates from Excel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <?php /* Capped so the dialog cannot grow with the file: the body
                     scrolls instead of the window. */ ?>
            <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
                <!-- Step 1 -->
                <div id="sheet_step_pick">
                    <p class="text-muted small">
                        Upload an <strong>.xlsx</strong> file whose first sheet has these five columns, in this order.
                        The heading row is ignored, so name the headings whatever you like.
                    </p>

                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-glass mb-0">
                            <thead>
                                <tr>
                                    <th>A</th><th>B</th><th>C</th><th>D</th><th>E</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Candidate name <span class="badge badge-glass-indigo">required</span></td>
                                    <td>Nee / maiden name</td>
                                    <td>Eligibility case no. <span class="badge badge-glass-indigo">required</span></td>
                                    <td>Verification remarks</td>
                                    <td>Email</td>
                                </tr>
                                <tr class="text-muted small">
                                    <td>Priya Sharma</td>
                                    <td>Priya Deshmukh</td>
                                    <td>IDOL/2026/0142</td>
                                    <td>Marksheet Verification</td>
                                    <td>priya@example.com</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-2 align-items-center">
                        <div class="col-sm-8">
                            <input type="file" class="form-control" id="candidate_sheet_file" accept=".xlsx">
                        </div>
                        <div class="col-sm-4 d-grid">
                            <button type="button" class="btn btn-emerald" id="btn_read_sheet">
                                <i class="fa fa-search me-1"></i> Read Sheet
                            </button>
                        </div>
                    </div>

                    <p class="text-muted small mt-2 mb-0">
                        Blank rows are skipped. Nothing is saved until you press
                        <strong>Save &amp; Generate PDF</strong> on the form behind this window.
                    </p>
                </div>

                <!-- Step 2 -->
                <div id="sheet_step_review" style="display:none;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                        <div>
                            <span class="badge badge-glass-emerald" id="sheet_ok_count">0 usable</span>
                            <span class="badge badge-glass-danger ms-1" id="sheet_error_count">0 with problems</span>
                            <span class="text-muted small ms-2" id="sheet_name_label"></span>
                        </div>
                        <div>
                            <button type="button" class="btn btn-sm btn-glass" id="btn_sheet_select_all">Select all usable</button>
                            <button type="button" class="btn btn-sm btn-glass" id="btn_sheet_select_none">Clear</button>
                            <button type="button" class="btn btn-sm btn-glass text-primary" id="btn_sheet_back">
                                <i class="fa fa-arrow-left me-1"></i> Choose another file
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-warning py-2 small" id="sheet_truncated_note" style="display:none;"></div>

                    <div class="table-responsive" style="max-height:46vh; overflow-y:auto;">
                        <table class="table table-sm table-glass table-sticky-id mb-0" id="sheet_preview_table">
                            <thead>
                                <tr>
                                    <th style="width:2.5rem;">
                                        <input type="checkbox" class="form-check-input" id="sheet_check_all" title="Select all usable rows">
                                    </th>
                                    <th style="width:4rem;">Row</th>
                                    <th>Candidate name</th>
                                    <th>Nee / maiden</th>
                                    <th>Case no.</th>
                                    <th>Remarks</th>
                                    <th>Email</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <span class="text-muted small me-auto" id="sheet_selection_label"></span>
                <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-emerald" id="btn_sheet_add" disabled>
                    <i class="fa fa-plus me-1"></i> Add Selected to List
                </button>
            </div>
        </div>
    </div>
</div>


<?php /*
  Bulk Update.

  Deliberately field-by-field opt-in: each field is only written to the
  selected candidates when its own "apply" box is ticked. A blank box that was
  not ticked must never blank out ten people's data -- which is exactly what a
  naive "edit these together" form does.
*/ ?>
<div class="modal fade" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkEditLabel">
                    <i class="fa fa-pencil-square-o me-2 text-primary"></i>Bulk Update Candidates
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body" style="max-height:65vh; overflow-y:auto;">
                <div class="alert alert-glass py-2 small mb-3" id="bulk_edit_intro"></div>

                <p class="text-muted small">
                    Tick a field to change it. Anything left unticked is kept exactly as it is
                    on every selected candidate.
                </p>

                <div class="row g-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input bulk-apply" type="checkbox" id="bulk_apply_nee" data-target="bulk_nee">
                            <label class="form-check-label fw-semibold" for="bulk_apply_nee">Nee / Maiden Name</label>
                        </div>
                        <input type="text" class="form-control mt-1" id="bulk_nee" disabled
                               placeholder="Leave blank to set it to '-'">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input bulk-apply" type="checkbox" id="bulk_apply_verify" data-target="bulk_verify">
                            <label class="form-check-label fw-semibold" for="bulk_apply_verify">Verification Remarks</label>
                        </div>
                        <input type="text" class="form-control mt-1" id="bulk_verify" disabled
                               placeholder="Leave blank to set it to 'Marksheet Verification'">
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input bulk-apply" type="checkbox" id="bulk_apply_email" data-target="bulk_email">
                            <label class="form-check-label fw-semibold" for="bulk_apply_email">Email</label>
                        </div>
                        <input type="email" class="form-control mt-1" id="bulk_email" disabled
                               placeholder="Leave blank to clear the email on every selected candidate">
                    </div>
                </div>

                <?php /* Name and case number are per-candidate identity, so they
                         are NOT offered here -- setting ten people to one name or
                         one case number is never the intent, and the case number
                         is what the dispatch letter is keyed on. */ ?>
                <p class="text-muted small mt-3 mb-0">
                    <i class="fa fa-info-circle me-1"></i>
                    Candidate name and eligibility case number are edited one at a time,
                    with the pencil on each row -- they identify the candidate.
                </p>

                <div class="table-responsive mt-3" style="max-height:28vh; overflow-y:auto;">
                    <table class="table table-sm table-glass mb-0" id="bulk_edit_preview">
                        <thead>
                            <tr><th>#</th><th>Candidate</th><th>Case no.</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btn_bulk_apply">
                    <i class="fa fa-check me-1"></i> Apply to Selected
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>



<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_students_new_js') ?>
<?= $this->endSection() ?>
