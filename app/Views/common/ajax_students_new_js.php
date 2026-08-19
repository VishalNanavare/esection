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
    $('#btn_add_student').on('click', function () {
        var name        = $('#stud_name').val().trim();
        var neeName     = $('#stud_nee_name').val().trim() || '-';
        var caseNo      = $('#eligibility_case_no').val().trim();
        var verifyByYou = $('#verification_by_you').val().trim() || 'Marksheet Verification';
        var email       = $('#stud_email').val().trim();

        if (!name || !caseNo) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing required fields',
                    text: 'Please enter the candidate full name and the eligibility case number.',
                    confirmButtonText: 'OK'
                });
            }
            return;
        }

        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            if (window.Swal) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Email looks incorrect',
                    text: 'Leave it blank or enter a valid address, e.g. name@example.com.',
                    confirmButtonText: 'OK'
                });
            }
            return;
        }

        studentList.push({
            student_name: name,
            student_nee_name: neeName,
            eligibility_case_no: caseNo,
            verification_by_you: verifyByYou,
            email: email
        });

        renderStudentTable();

        $('#stud_name, #stud_nee_name, #verification_by_you, #stud_email').val('');
        $('#stud_name').trigger('focus');

        // Refill with a fresh suggestion (Settings > Document Numbering
        // format) rather than leaving it blank -- fully editable, same as
        // the one that was just accepted.
        $.get('<?= base_url("students/generateCaseNo") ?>').done(function (res) {
            if (res && res.case_no) {
                $('#eligibility_case_no').val(res.case_no);
            }
        });

        esNotify('success', 'Candidate added to the batch');
    });


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

    function renderStudentTable() {
        var tbody = $('#student_batch_table tbody');
        tbody.empty();

        $('#batch_count').text(studentList.length);

        if (studentList.length === 0) {
            tbody.append(
                '<tr id="empty_row"><td colspan="7" class="text-center text-muted py-4">' +
                'No candidates added to this batch yet.</td></tr>'
            );
            return;
        }

        // Every interpolated value is escaped: these strings are operator
        // input and were previously concatenated straight into innerHTML,
        // so a candidate name containing markup executed immediately.
        $.each(studentList, function (idx, item) {
            tbody.append(
                '<tr>' +
                    '<td>' + (idx + 1) + '</td>' +
                    '<td class="fw-bold text-dark">' + esEscapeHtml(item.student_name) + '</td>' +
                    '<td>' + esEscapeHtml(item.student_nee_name) + '</td>' +
                    '<td><span class="badge badge-glass-indigo">' + esEscapeHtml(item.eligibility_case_no) + '</span></td>' +
                    '<td>' + esEscapeHtml(item.verification_by_you) + '</td>' +
                    '<td class="small text-muted">' + esEscapeHtml(item.email || '-') + '</td>' +
                    '<td class="text-end">' +
                        '<button type="button" class="btn btn-sm btn-glass text-danger remove-row" ' +
                                'data-index="' + idx + '" title="Remove candidate">' +
                            '<i class="fa fa-trash"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>'
            );
        });
    }

    $(document).on('click', '.remove-row', function () {
        studentList.splice($(this).data('index'), 1);
        renderStudentTable();
    });

    // --- Save batch and generate the dispatch letter -----------------------
    $('#btn_save_and_pdf').on('click', function () {
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
            students:             studentList
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
