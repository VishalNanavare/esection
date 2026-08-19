<script {csp-script-nonce}>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-college', '<?= base_url("api/colleges") ?>', {
        context: 'Loading universities failed'
    });

    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    esAjaxSelect('.select2-ajax-academic-year', '<?= base_url("api/academic-years") ?>', {
        context: 'Loading academic years failed'
    });

    var studentList = [];

    /* -----------------------------------------------------------------
       Rows are identified by a stable uid, never by array index.

       Index identity breaks the moment the list is mutated: removing row
       2 renumbers everything below it, so a selection, an open edit or a
       queued delete captured beforehand silently points at a DIFFERENT
       candidate. With bulk delete removing several at once, that is not a
       corner case -- it is the normal path. Every row therefore carries a
       uid, and selection/edit/delete all address rows by it.
       ----------------------------------------------------------------- */
    var uidSeq = 0;

    function nextUid() {
        uidSeq += 1;
        return 'c' + uidSeq;
    }

    // uid of the row being edited, or null while adding a new one.
    var editingUid = null;

    // uids currently ticked. A Set-like object keyed by uid.
    var selectedUids = {};

    // Current name filter, lower-cased. '' means show everything.
    var batchFilter = '';

    function indexOfUid(uid) {
        for (var i = 0; i < studentList.length; i++) {
            if (studentList[i].uid === uid) {
                return i;
            }
        }
        return -1;
    }

    function selectedUidList() {
        var out = [];
        for (var uid in selectedUids) {
            if (selectedUids.hasOwnProperty(uid) && indexOfUid(uid) !== -1) {
                out.push(uid);
            }
        }
        return out;
    }

    // Rows matching the current filter, in list order.
    function visibleRows() {
        if (batchFilter === '') {
            return studentList.slice();
        }

        return $.grep(studentList, function (item) {
            return (item.student_name || '').toLowerCase().indexOf(batchFilter) !== -1;
        });
    }


    // --- Auto-fill university details on selection -------------------------
    $('#clg_add_select').on('change', function () {
        var colId = $(this).val();
        if (!colId) {
            return;
        }

        $.ajax({
            url: '<?= base_url("students/getCollegeInfo/") ?>' + encodeURIComponent(colId),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success') {
                esNotify('warning', 'University details unavailable', (res && res.message) || 'Please fill the address manually.');
                return;
            }

            $('#clg_add').val(res.address);
            $('#In_Favour_of').val(res.in_favour_of);
            $('#to').val(res.head_name || 'The Controller of Examinations');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the university details');
        });
    });

    // --- Batch list --------------------------------------------------------
    // Reads and validates the five entry-panel inputs the SAME way whether the
    // operator is adding a new candidate or editing an existing one. Returns a
    // clean record, or null after saying what is wrong. The two defaults match
    // what the batch save and the Excel importer apply, so a row typed here is
    // indistinguishable from one filled any other way.
    function collectEntryPanel() {
        var name        = $('#stud_name').val().trim();
        var neeName     = $('#stud_nee_name').val().trim() || '-';
        var caseNo      = $('#eligibility_case_no').val().trim();
        var verifyByYou = $('#verification_by_you').val().trim() || 'Marksheet Verification';
        var email       = $('#stud_email').val().trim();

        if (!name || !caseNo) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing required fields',
                text: 'Please enter the candidate full name and the eligibility case number.',
                confirmButtonText: 'OK'
            });
            return null;
        }

        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            Swal.fire({
                icon: 'warning',
                title: 'Email looks incorrect',
                text: 'Leave it blank or enter a valid address, e.g. name@example.com.',
                confirmButtonText: 'OK'
            });
            return null;
        }

        return {
            student_name: name,
            student_nee_name: neeName,
            eligibility_case_no: caseNo,
            verification_by_you: verifyByYou,
            email: email
        };
    }

    // Empties the panel. refillCaseNo asks the server for the next suggested
    // case number (Settings > Document Numbering) rather than leaving it blank
    // -- fully editable, same as the one just used.
    function clearEntryPanel(refillCaseNo) {
        $('#stud_name, #stud_nee_name, #verification_by_you, #stud_email, #eligibility_case_no').val('');

        if (refillCaseNo) {
            $.get('<?= base_url("students/generateCaseNo") ?>').done(function (res) {
                if (res && res.case_no) {
                    $('#eligibility_case_no').val(res.case_no);
                }
            });
        }
    }

    // Loads a row back into the panel and switches it to "update this row".
    function enterEditMode(uid) {
        var idx  = indexOfUid(uid);
        var item = idx === -1 ? null : studentList[idx];

        if (!item) {
            return;
        }

        editingUid = uid;

        // The two defaults are shown as blank so the operator sees the same
        // placeholder they typed against, not a value they never entered.
        $('#stud_name').val(item.student_name);
        $('#stud_nee_name').val(item.student_nee_name === '-' ? '' : item.student_nee_name);
        $('#eligibility_case_no').val(item.eligibility_case_no);
        $('#verification_by_you').val(item.verification_by_you === 'Marksheet Verification' ? '' : item.verification_by_you);
        $('#stud_email').val(item.email || '');

        $('#btn_add_student').html('<i class="fa fa-check me-1"></i> Update Candidate');
        $('#btn_cancel_edit').show();

        renderStudentTable();          // marks the row being edited
        $('#stud_name').trigger('focus');
    }

    // Back to "add a new candidate".
    function exitEditMode() {
        editingUid = null;
        $('#btn_add_student').html('<i class="fa fa-plus me-1"></i> Add Candidate to List');
        $('#btn_cancel_edit').hide();
        clearEntryPanel(true);
        renderStudentTable();
    }

    $('#btn_add_student').on('click', function () {
        var record = collectEntryPanel();

        if (record === null) {
            return;
        }

        if (editingUid === null) {
            record.uid = nextUid();
            studentList.push(record);
            renderStudentTable();
            clearEntryPanel(true);
            $('#stud_name').trigger('focus');
            esNotify('success', 'Candidate added to the batch');
            return;
        }

        var editIdx = indexOfUid(editingUid);

        if (editIdx === -1) {
            // The row was removed while it was being edited.
            exitEditMode();
            esNotify('warning', 'That candidate is no longer in the batch');
            return;
        }

        record.uid = editingUid;
        studentList[editIdx] = record;

        var updatedUid = editingUid;
        exitEditMode();
        flashRow(updatedUid);
        esNotify('success', 'Candidate updated');
    });

    $('#btn_cancel_edit').on('click', exitEditMode);


    /* =================================================================
       Fill from Excel
       -----------------------------------------------------------------
       Reads a five-column sheet server-side and drops the ticked rows
       into studentList, the same array the manual "Add Candidate" button
       feeds. Nothing is saved here: the batch is written only when the
       operator presses Save & Generate PDF, through the same validated
       storeBatch() path a typed batch uses.
       ================================================================= */

    // Rows as returned by students/new/readSheet, in sheet order.
    var sheetRows = [];

    // Above this many, the rows go in a chunk at a time behind a progress
    // dialog. Adding is cheap, but a single synchronous loop over a few
    // hundred rows repaints the table once at the end and looks frozen --
    // and the operator gets no sense of how much is left.
    var SHEET_CHUNK_THRESHOLD = 10;
    var SHEET_CHUNK_SIZE      = 10;

    function sheetResetToPicker() {
        sheetRows = [];
        $('#sheet_step_review').hide();
        $('#sheet_step_pick').show();
        $('#sheet_preview_table tbody').empty();
        $('#candidate_sheet_file').val('');
        $('#sheet_truncated_note').hide().text('');
        $('#btn_sheet_add').prop('disabled', true);
        $('#sheet_selection_label').text('');
        $('#sheet_check_all').prop('checked', false);
    }

    function sheetSelectedIndexes() {
        var picked = [];

        $('#sheet_preview_table tbody input.sheet-row-check:checked').each(function () {
            picked.push(parseInt($(this).val(), 10));
        });

        return picked;
    }

    function sheetRefreshSelectionLabel() {
        var count = sheetSelectedIndexes().length;

        $('#btn_sheet_add').prop('disabled', count === 0);
        $('#sheet_selection_label').text(count === 0 ? '' : count + ' row(s) selected');
    }

    function sheetRenderPreview(data) {
        var tbody = $('#sheet_preview_table tbody');
        tbody.empty();

        $('#sheet_ok_count').text(data.ok_count + ' usable');
        $('#sheet_error_count').text(data.error_count + ' with problems');
        $('#sheet_name_label').text(data.sheet ? 'Sheet: ' + data.sheet : '');

        if (data.truncated) {
            $('#sheet_truncated_note')
                .text('Only the first ' + data.rows.length + ' rows were read. One batch cannot hold more than that, '
                      + 'so split the file and import the rest as a second batch.')
                .show();
        }

        $.each(data.rows, function (idx, row) {
            var usable   = row.status === 'ok';
            var problems = (row.messages || []).join(' ');

            // Every value here came out of somebody's spreadsheet, so it is
            // escaped on the way into the DOM -- same rule as
            // renderStudentTable() below.
            tbody.append(
                '<tr class="' + (usable ? '' : 'table-warning') + '">' +
                    '<td>' +
                        (usable
                            ? '<input type="checkbox" class="form-check-input sheet-row-check" value="' + idx + '" checked>'
                            : '<i class="fa fa-ban text-danger" title="' + esEscapeHtml(problems) + '"></i>') +
                    '</td>' +
                    '<td class="text-muted small">' + row.line + '</td>' +
                    '<td class="fw-bold text-dark">' + esEscapeHtml(row.data.student_name) + '</td>' +
                    '<td>' + esEscapeHtml(row.data.student_nee_name) + '</td>' +
                    '<td><span class="badge badge-glass-indigo">' + esEscapeHtml(row.data.eligibility_case_no) + '</span></td>' +
                    '<td class="small">' + esEscapeHtml(row.data.verification_by_you) + '</td>' +
                    '<td class="small text-muted">' + esEscapeHtml(row.data.email || '-') + '</td>' +
                '</tr>'
            );

            if (!usable) {
                tbody.append(
                    '<tr class="table-warning">' +
                        '<td></td>' +
                        '<td colspan="6" class="small text-danger pt-0">' +
                            '<i class="fa fa-exclamation-triangle me-1"></i>' + esEscapeHtml(problems) +
                        '</td>' +
                    '</tr>'
                );
            }
        });

        $('#sheet_check_all').prop('checked', data.ok_count > 0);
        $('#sheet_step_pick').hide();
        $('#sheet_step_review').show();
        sheetRefreshSelectionLabel();
    }

    $('#btn_read_sheet').on('click', function () {
        var input = $('#candidate_sheet_file')[0];

        if (!input || !input.files || input.files.length === 0) {
            Swal.fire({ icon: 'warning', title: 'No file chosen', text: 'Pick an .xlsx file first.' });
            return;
        }

        var form = new FormData();
        form.append('candidate_sheet', input.files[0]);
        form.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');

        var button = $(this).prop('disabled', true);
        button.html('<i class="fa fa-spinner fa-spin me-1"></i> Reading...');

        $.ajax({
            url:         '<?= base_url("students/new/readSheet") ?>',
            type:        'POST',
            data:        form,
            processData: false,
            contentType: false,
            dataType:    'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data || !res.data.rows.length) {
                Swal.fire({ icon: 'error', title: 'Nothing to import', text: 'That sheet had no candidate rows.' });
                return;
            }

            sheetRows = res.data.rows;
            sheetRenderPreview(res.data);
        }).fail(function (xhr) {
            var message = 'That sheet could not be read.';

            if (xhr.responseJSON && xhr.responseJSON.message) {
                message = xhr.responseJSON.message;
            }

            Swal.fire({ icon: 'error', title: 'Sheet not accepted', text: message });
        }).always(function () {
            button.prop('disabled', false).html('<i class="fa fa-search me-1"></i> Read Sheet');
        });
    });

    $('#btn_sheet_back').on('click', sheetResetToPicker);

    // Any dismissal -- Cancel, the X, backdrop, Esc -- returns the modal to the
    // upload step. Without this, closing after a read left the previous file's
    // preview (and its sheetRows) in place, so reopening showed step 2 for a
    // file the operator had already moved on from.
    $('#candidateSheetModal').on('hidden.bs.modal', sheetResetToPicker);

    $('#sheet_check_all').on('change', function () {
        $('#sheet_preview_table tbody input.sheet-row-check').prop('checked', $(this).is(':checked'));
        sheetRefreshSelectionLabel();
    });

    $('#btn_sheet_select_all').on('click', function () {
        $('#sheet_preview_table tbody input.sheet-row-check').prop('checked', true);
        $('#sheet_check_all').prop('checked', true);
        sheetRefreshSelectionLabel();
    });

    $('#btn_sheet_select_none').on('click', function () {
        $('#sheet_preview_table tbody input.sheet-row-check').prop('checked', false);
        $('#sheet_check_all').prop('checked', false);
        sheetRefreshSelectionLabel();
    });

    $(document).on('change', '#sheet_preview_table tbody input.sheet-row-check', sheetRefreshSelectionLabel);

    /**
     * Copies the ticked rows into studentList.
     *
     * Duplicate case numbers are skipped rather than added twice -- the case
     * number is what identifies a candidate on the letter, and the same sheet
     * read twice is an easy mistake to make.
     */
    $('#btn_sheet_add').on('click', function () {
        var picked = sheetSelectedIndexes();

        if (picked.length === 0) {
            return;
        }

        var existing = {};
        $.each(studentList, function (i, item) {
            existing[(item.eligibility_case_no || '').toLowerCase()] = true;
        });

        var queued = [];
        var skipped = 0;

        $.each(picked, function (i, index) {
            var row = sheetRows[index];

            if (!row || row.status !== 'ok') {
                return;
            }

            var key = (row.data.eligibility_case_no || '').toLowerCase();

            if (existing[key]) {
                skipped++;
                return;
            }

            existing[key] = true;
            queued.push({
                // Imported rows get a uid like any other. Without one they
                // would render with an empty data-uid, so selecting, editing or
                // removing an imported candidate would address nothing.
                uid:                 nextUid(),
                student_name:        row.data.student_name,
                student_nee_name:    row.data.student_nee_name,
                eligibility_case_no: row.data.eligibility_case_no,
                verification_by_you: row.data.verification_by_you,
                email:               row.data.email
            });
        });

        if (queued.length === 0) {
            Swal.fire({
                icon:  'info',
                title: 'Nothing new to add',
                text:  skipped > 0
                    ? 'Every selected candidate is already in the batch (matched on eligibility case number).'
                    : 'No usable rows were selected.'
            });
            return;
        }

        var modalEl = document.getElementById('candidateSheetModal');
        var modal   = window.bootstrap ? bootstrap.Modal.getInstance(modalEl) : null;

        function finish() {
            renderStudentTable();

            if (modal) {
                modal.hide();
            }

            sheetResetToPicker();

            Swal.fire({
                icon:  'success',
                title: 'Candidates added',
                html:  '<strong>' + queued.length + '</strong> candidate(s) added to the batch.'
                       + (skipped > 0 ? '<br><span class="text-muted small">' + skipped
                          + ' skipped as already present.</span>' : '')
                       + '<br><span class="text-muted small">Nothing is saved yet -- choose the university, '
                       + 'year and course, then press Save &amp; Generate PDF.</span>'
            });
        }

        // Small lists go straight in; the dialog would flash past.
        if (queued.length <= SHEET_CHUNK_THRESHOLD) {
            Array.prototype.push.apply(studentList, queued);
            finish();
            return;
        }

        // Larger ones go a chunk at a time, yielding to the browser between
        // chunks so the bar genuinely moves instead of jumping to 100% after
        // a frozen pause.
        var added = 0;

        Swal.fire({
            title: 'Adding candidates',
            html:
                '<div class="progress" style="height:1.25rem;">' +
                    '<div id="sheet_progress_bar" class="progress-bar progress-bar-striped progress-bar-animated" ' +
                         'role="progressbar" style="width:0%;">0%</div>' +
                '</div>' +
                '<p class="text-muted small mt-2 mb-0" id="sheet_progress_text">0 of ' + queued.length + '</p>',
            allowOutsideClick: false,
            allowEscapeKey:    false,
            showConfirmButton: false,
            didOpen: function () {
                function step() {
                    var slice = queued.slice(added, added + SHEET_CHUNK_SIZE);
                    Array.prototype.push.apply(studentList, slice);
                    added += slice.length;

                    var percent = Math.round((added / queued.length) * 100);
                    $('#sheet_progress_bar').css('width', percent + '%').text(percent + '%');
                    $('#sheet_progress_text').text(added + ' of ' + queued.length);

                    if (added < queued.length) {
                        setTimeout(step, 60);
                        return;
                    }

                    setTimeout(function () {
                        Swal.close();
                        finish();
                    }, 250);
                }

                step();
            }
        });
    });

    function renderStudentTable(animateUids) {
        var tbody = $('#student_batch_table tbody');
        tbody.empty();

        $('#batch_count').text(studentList.length);

        if (studentList.length === 0) {
            tbody.append(
                '<tr id="empty_row"><td colspan="8" class="text-center text-muted py-4">' +
                'No candidates added to this batch yet.</td></tr>'
            );
            refreshBatchToolbar();
            return;
        }

        var shown = visibleRows();

        if (shown.length === 0) {
            tbody.append(
                '<tr id="empty_row"><td colspan="8" class="text-center text-muted py-4">' +
                'No candidate matches &ldquo;' + esEscapeHtml(batchFilter) + '&rdquo;.</td></tr>'
            );
            refreshBatchToolbar();
            return;
        }

        // Every interpolated value is escaped: these strings are operator
        // input and were previously concatenated straight into innerHTML,
        // so a candidate name containing markup executed immediately.
        $.each(studentList, function (idx, item) {
            // Filtered-out rows are still RENDERED, just hidden. Removing them
            // from the DOM would drop their checkbox, and a row hidden by a
            // filter is still selected -- the operator narrowed the view, they
            // did not deselect anyone.
            var hidden   = batchFilter !== ''
                && (item.student_name || '').toLowerCase().indexOf(batchFilter) === -1;
            var selected = selectedUids.hasOwnProperty(item.uid);

            var classes = [];
            if (hidden)                { classes.push('es-row-filtered'); }
            if (selected)              { classes.push('es-row-selected'); }
            if (item.uid === editingUid) { classes.push('es-row-editing'); }
            if (animateUids && animateUids[item.uid] && !esReducedMotion()) { classes.push('es-row-in'); }

            tbody.append(
                '<tr data-uid="' + esEscapeHtml(item.uid) + '"' +
                    (classes.length ? ' class="' + classes.join(' ') + '"' : '') + '>' +
                    '<td>' +
                        '<input type="checkbox" class="form-check-input batch-row-check" ' +
                               'value="' + esEscapeHtml(item.uid) + '"' + (selected ? ' checked' : '') +
                               ' aria-label="Select ' + esEscapeHtml(item.student_name) + '">' +
                    '</td>' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td class="fw-bold text-dark">' + esEscapeHtml(item.student_name) + '</td>' +
                    '<td>' + esEscapeHtml(item.student_nee_name) + '</td>' +
                    '<td><span class="badge badge-glass-indigo">' + esEscapeHtml(item.eligibility_case_no) + '</span></td>' +
                    '<td>' + esEscapeHtml(item.verification_by_you) + '</td>' +
                    '<td class="small text-muted">' + esEscapeHtml(item.email || '-') + '</td>' +
                    '<td class="text-end text-nowrap">' +
                        '<button type="button" class="btn btn-sm btn-glass text-primary edit-row me-1" ' +
                                'data-uid="' + esEscapeHtml(item.uid) + '" title="Edit candidate">' +
                            '<i class="fa fa-pencil"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-sm btn-glass text-danger remove-row" ' +
                                'data-uid="' + esEscapeHtml(item.uid) + '" title="Remove candidate">' +
                            '<i class="fa fa-trash"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>'
            );
        });

        refreshBatchToolbar();
    }

    // Briefly highlights a row so an update is visible where it happened,
    // rather than only in a toast at the corner of the screen.
    function flashRow(uid) {
        if (esReducedMotion()) {
            return;
        }

        var $row = $('#student_batch_table tbody tr[data-uid="' + uid + '"]');
        $row.addClass('es-row-flash');
        setTimeout(function () { $row.removeClass('es-row-flash'); }, 950);
    }

    // Keeps the toolbar honest about what is selected and what is shown.
    function refreshBatchToolbar() {
        var selected = selectedUidList();
        var shown    = visibleRows();

        $('#btn_bulk_edit,#btn_bulk_delete').prop('disabled', selected.length === 0);

        if (selected.length === 0) {
            $('#batch_selection_badge').hide().text('');
        } else {
            $('#batch_selection_badge').show().text(selected.length + ' selected');
        }

        $('#batch_filter_note').text(
            batchFilter === ''
                ? ''
                : 'Showing ' + shown.length + ' of ' + studentList.length
        );

        // The master box reflects only the rows currently SHOWN, because that
        // is what clicking it will act on.
        var shownSelected = 0;
        $.each(shown, function (i, item) {
            if (selectedUids.hasOwnProperty(item.uid)) {
                shownSelected += 1;
            }
        });

        var $all = $('#batch_check_all');
        $all.prop('checked', shown.length > 0 && shownSelected === shown.length);
        $all.prop('indeterminate', shownSelected > 0 && shownSelected < shown.length);
    }

    /* --- Filter ------------------------------------------------------- */

    // Debounced so a fast typist does not repaint the table on every keystroke.
    var filterTimer = null;

    $('#batch_filter').on('input', function () {
        var value = $(this).val();

        // The clear button appears only once there is something to clear.
        $(this).closest('.es-search').toggleClass('es-search--filled', $.trim(value) !== '');

        clearTimeout(filterTimer);
        filterTimer = setTimeout(function () {
            batchFilter = $.trim(value).toLowerCase();
            renderStudentTable();
        }, 150);
    });

    $('#btn_clear_filter').on('click', function () {
        $('#batch_filter').val('').closest('.es-search').removeClass('es-search--filled');
        batchFilter = '';
        renderStudentTable();
        $('#batch_filter').trigger('focus');
    });

    /* --- Selection ---------------------------------------------------- */

    $(document).on('change', '.batch-row-check', function () {
        var uid = $(this).val();

        if ($(this).is(':checked')) {
            selectedUids[uid] = true;
            $(this).closest('tr').addClass('es-row-selected');
        } else {
            delete selectedUids[uid];
            $(this).closest('tr').removeClass('es-row-selected');
        }

        refreshBatchToolbar();
    });

    $('#batch_check_all').on('change', function () {
        var check = $(this).is(':checked');

        // Acts on the SHOWN rows only. Ticking "select all" while a filter is
        // active must not quietly select candidates the operator cannot see.
        $.each(visibleRows(), function (i, item) {
            if (check) {
                selectedUids[item.uid] = true;
            } else {
                delete selectedUids[item.uid];
            }
        });

        renderStudentTable();
    });

    /* --- Row actions -------------------------------------------------- */

    $(document).on('click', '.edit-row', function () {
        enterEditMode($(this).data('uid'));
    });

    // Removes rows with a slide-out, then repaints. Shared by the single-row
    // trash button and the bulk remove, so both animate identically.
    function removeRowsByUid(uids) {
        if (!uids.length) {
            return;
        }

        // An edit in progress on a row being removed cannot survive it.
        if (editingUid !== null && $.inArray(editingUid, uids) !== -1) {
            exitEditMode();
        }

        var $rows = $();
        $.each(uids, function (i, uid) {
            delete selectedUids[uid];
            $rows = $rows.add('#student_batch_table tbody tr[data-uid="' + uid + '"]');
        });

        function commit() {
            studentList = $.grep(studentList, function (item) {
                return $.inArray(item.uid, uids) === -1;
            });

            renderStudentTable();
            esNotify('success', uids.length === 1
                ? 'Candidate removed'
                : uids.length + ' candidates removed');
        }

        if (esReducedMotion() || $rows.length === 0) {
            commit();
            return;
        }

        $rows.addClass('es-row-out');

        // Held just past --es-dur-slow (480ms) so the row has finished sliding
        // out before the table repaints under it. Read from the stylesheet
        // rather than hardcoded, so retuning the motion scale cannot leave this
        // committing mid-animation.
        setTimeout(commit, esMotionMs('--es-dur-slow', 480) + 40);
    }

    $(document).on('click', '.remove-row', function () {
        var uid  = $(this).data('uid');
        var idx  = indexOfUid(uid);
        var item = idx === -1 ? null : studentList[idx];

        if (!item) {
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Remove this candidate?',
            html: '<strong>' + esEscapeHtml(item.student_name) + '</strong>'
                  + '<br><span class="text-muted small">' + esEscapeHtml(item.eligibility_case_no) + '</span>'
                  + '<br><br><span class="text-muted small">It is only removed from this list. '
                  + 'Nothing has been saved yet.</span>',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove',
            cancelButtonText: 'Keep it',
            confirmButtonColor: '#dc3545',
            reverseButtons: true,
            focusCancel: true
        }).then(function (result) {
            if (result.isConfirmed) {
                removeRowsByUid([uid]);
            }
        });
    });

    /* --- Bulk remove -------------------------------------------------- */

    $('#btn_bulk_delete').on('click', function () {
        var uids = selectedUidList();

        if (uids.length === 0) {
            return;
        }

        var names = $.map(uids.slice(0, 5), function (uid) {
            var idx = indexOfUid(uid);
            return idx === -1 ? null : esEscapeHtml(studentList[idx].student_name);
        }).join('<br>');

        var more = uids.length > 5 ? '<br><span class="text-muted small">and ' + (uids.length - 5) + ' more</span>' : '';

        Swal.fire({
            icon: 'warning',
            title: 'Remove ' + uids.length + ' candidate' + (uids.length === 1 ? '' : 's') + '?',
            html: names + more
                  + '<br><br><span class="text-muted small">They are only removed from this list. '
                  + 'Nothing has been saved yet.</span>',
            showCancelButton: true,
            confirmButtonText: 'Yes, remove ' + uids.length,
            cancelButtonText: 'Keep them',
            confirmButtonColor: '#dc3545',
            reverseButtons: true,
            focusCancel: true
        }).then(function (result) {
            if (result.isConfirmed) {
                removeRowsByUid(uids);
            }
        });
    });

    /* --- Bulk update -------------------------------------------------- */

    // Each field is written ONLY when its own box is ticked, so an untouched
    // blank input can never wipe a field across every selected candidate.
    $(document).on('change', '.bulk-apply', function () {
        var $input = $('#' + $(this).data('target'));
        $input.prop('disabled', !$(this).is(':checked'));

        if ($(this).is(':checked')) {
            $input.trigger('focus');
        }
    });

    $('#btn_bulk_edit').on('click', function () {
        var uids = selectedUidList();

        if (uids.length === 0) {
            return;
        }

        $('.bulk-apply').prop('checked', false).trigger('change');
        $('#bulk_nee, #bulk_verify, #bulk_email').val('');

        $('#bulk_edit_intro').html(
            '<i class="fa fa-users me-1"></i> Changing <strong>' + uids.length +
            '</strong> selected candidate' + (uids.length === 1 ? '' : 's') + '.'
        );

        var $body = $('#bulk_edit_preview tbody').empty();

        $.each(uids, function (i, uid) {
            var idx = indexOfUid(uid);

            if (idx === -1) {
                return;
            }

            var item = studentList[idx];

            $body.append(
                '<tr>' +
                    '<td class="text-muted small">' + (i + 1) + '</td>' +
                    '<td>' + esEscapeHtml(item.student_name) + '</td>' +
                    '<td class="small text-muted">' + esEscapeHtml(item.eligibility_case_no) + '</td>' +
                '</tr>'
            );
        });

        new bootstrap.Modal(document.getElementById('bulkEditModal')).show();
    });

    $('#btn_bulk_apply').on('click', function () {
        var uids = selectedUidList();

        if (uids.length === 0) {
            return;
        }

        var changes = {};

        if ($('#bulk_apply_nee').is(':checked')) {
            changes.student_nee_name = $('#bulk_nee').val().trim() || '-';
        }

        if ($('#bulk_apply_verify').is(':checked')) {
            changes.verification_by_you = $('#bulk_verify').val().trim() || 'Marksheet Verification';
        }

        if ($('#bulk_apply_email').is(':checked')) {
            var email = $('#bulk_email').val().trim();

            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Email looks incorrect',
                    text: 'Leave it blank to clear the address, or enter a valid one.'
                });
                return;
            }

            changes.email = email;
        }

        if ($.isEmptyObject(changes)) {
            Swal.fire({
                icon: 'info',
                title: 'Nothing to change',
                text: 'Tick at least one field to apply.'
            });
            return;
        }

        var summary = $.map(changes, function (value, key) {
            var label = key === 'student_nee_name' ? 'Nee / maiden name'
                      : key === 'verification_by_you' ? 'Verification remarks'
                      : 'Email';

            return '<li>' + label + ' &rarr; <strong>' +
                   esEscapeHtml(value === '' ? '(cleared)' : value) + '</strong></li>';
        }).join('');

        Swal.fire({
            icon: 'question',
            title: 'Apply to ' + uids.length + ' candidate' + (uids.length === 1 ? '' : 's') + '?',
            html: '<ul class="text-start small mb-0">' + summary + '</ul>',
            showCancelButton: true,
            confirmButtonText: 'Yes, apply',
            cancelButtonText: 'Back',
            reverseButtons: true
        }).then(function (result) {
            if (!result.isConfirmed) {
                return;
            }

            var applied = 0;

            $.each(uids, function (i, uid) {
                var idx = indexOfUid(uid);

                if (idx === -1) {
                    return;
                }

                $.each(changes, function (key, value) {
                    studentList[idx][key] = value;
                });

                applied += 1;
            });

            var modal = bootstrap.Modal.getInstance(document.getElementById('bulkEditModal'));

            if (modal) {
                modal.hide();
            }

            renderStudentTable();

            // Flash each changed row so the effect is visible in the table.
            $.each(uids, function (i, uid) { flashRow(uid); });

            esNotify('success', applied + ' candidate' + (applied === 1 ? '' : 's') + ' updated');
        });
    });

    // --- Save batch and generate the dispatch letter -----------------------
    $('#btn_save_and_pdf').on('click', function () {
        // Fold an open edit in first. While a row is being edited, the
        // corrected values live only in the entry-panel inputs -- they reach
        // studentList only when "Update Candidate" is clicked. Saving without
        // that click would post the row's OLD values, and because the table
        // still shows the right number of rows with the correction sitting
        // visibly in the inputs, nothing would signal the loss and the dispatch
        // letter would go out wrong. So commit the panel now; if it does not
        // validate, collectEntryPanel() has already said why and the save is
        // held until the operator fixes or cancels the edit.
        if (editingUid !== null) {
            var openEdit = collectEntryPanel();

            if (openEdit === null) {
                return;
            }

            var openIdx = indexOfUid(editingUid);

            if (openIdx !== -1) {
                openEdit.uid = editingUid;
                studentList[openIdx] = openEdit;
            }

            exitEditMode();
        }

        if (studentList.length === 0) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Empty candidate batch',
                    text: 'Please add at least one candidate before saving.',
                    confirmButtonText: 'OK'
                });
            }
            return;
        }

        var $btn = $(this).prop('disabled', true);

        var payload = {
            common_no:            $('#display_common_no').text(),
            to_name:              $('#to').val(),
            clg_add:              $('#clg_add').val(),
            admission_taken_year: $('#admission_taken_year').val(),
            admission_taken_in:   $('#admission_taken_in').val(),
            in_favour_of:         $('#In_Favour_of').val(),
            // uid is client-side row identity and has no business on the wire.
            // storeCandidateBatch() builds its insert from named keys so an
            // extra one is harmless, but sending it invites a future reader to
            // treat it as meaningful.
            students:             $.map(studentList, function (item) {
                return {
                    student_name:        item.student_name,
                    student_nee_name:    item.student_nee_name,
                    eligibility_case_no: item.eligibility_case_no,
                    verification_by_you: item.verification_by_you,
                    email:               item.email
                };
            })
        };

        if (window.Swal) {
            Swal.fire({
                title: 'Saving verification batch...',
                text: 'Records are being saved and the dispatch letter prepared.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function () {
                    Swal.showLoading();
                }
            });
        }

        $.ajax({
            url: '<?= base_url("students/storeBatch") ?>',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            headers: { 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' }
        }).done(function (res) {
            if (!res || res.status !== 'success') {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Save failed',
                        text: (res && res.message) || 'The batch could not be saved.'
                    });
                }
                $btn.prop('disabled', false);
                return;
            }

            // The PDF is opened from the click handler's continuation rather
            // than a timed dialog callback: window.open outside a user
            // gesture is blocked by default in Chrome and Firefox, which is
            // why the dispatch letter never appeared.
            var pdf = window.open(res.redirect_url, '_blank');

            if (window.Swal) {
                Swal.fire({
                    icon: 'success',
                    title: 'Verification batch saved',
                    text: res.message,
                    confirmButtonText: 'Go to dashboard'
                }).then(function () {
                    window.location.href = '<?= base_url("dashboard") ?>';
                });
            } else {
                window.location.href = '<?= base_url("dashboard") ?>';
            }

            if (!pdf) {
                esNotify('warning', 'Pop-up blocked', 'Allow pop-ups to open the dispatch letter automatically.');
            }
        }).fail(function (xhr) {
            $btn.prop('disabled', false);

            if (window.Swal) {
                Swal.close();
            }
            esAjaxError(xhr, 'The verification batch could not be saved');
        });
    });
});
</script>
