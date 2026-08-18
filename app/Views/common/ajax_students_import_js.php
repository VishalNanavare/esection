<script {csp-script-nonce}>
$(document).ready(function () {

    // The last preview the server returned. Held only to render the screen and
    // to know whether Confirm is allowed -- the rows themselves are NEVER
    // posted back. Commit re-sends the file and the server re-parses it, so
    // what gets written is always what the server read, not what the browser
    // was holding.
    var lastPreview = null;

    var CSRF = '<?= csrf_hash() ?>';

    function selectedFile() {
        var input = document.getElementById('import_file');
        return input && input.files && input.files.length ? input.files[0] : null;
    }

    // Checked client-side as well as server-side: nginx's own body limit fires
    // BEFORE PHP, and its 413 is an HTML page that a dataType:'json' handler
    // reports as a parse error rather than "file too large".
    function fileTooLarge(file) {
        var maxKb = parseInt($('#import_file').data('max-kb'), 10) || 2048;
        return file.size / 1024 > maxKb;
    }

    /** The mapping choices currently on screen, as { sourceValue: target }. */
    function collectMappings(tableId) {
        var out = {};
        $('#' + tableId + ' select').each(function () {
            var source = $(this).data('source');
            var value  = $(this).val();
            if (value) { out[source] = value; }
        });
        return out;
    }

    function buildFormData() {
        var file = selectedFile();
        if (!file) { return null; }

        var fd = new FormData();
        fd.append('import_file', file);

        $.each(collectMappings('board_map_table'), function (source, target) {
            fd.append('boards[' + source + ']', target);
        });
        $.each(collectMappings('course_map_table'), function (source, target) {
            fd.append('courses[' + source + ']', target);
        });

        // FormData built by hand carries no csrf_field(), so the token goes in
        // the header -- Security::getPostedToken() checks POST body, then
        // header, then JSON body.
        return fd;
    }

    /** @return the jqXHR, so callers can re-enable their button in .always() */
    function post(url, fd, onDone) {
        return $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            headers: { 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            if (!res || res.status !== 'success') {
                esNotify('error', 'Import', (res && res.message) || 'Unexpected response.');
                return;
            }
            onDone(res.data);
        }).fail(function (xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.message;
            if (msg) {
                esNotify('warning', 'Import', msg);
            } else {
                esAjaxError(xhr, 'The file could not be processed');
            }
        });
    }

    // --- Preview ---------------------------------------------------------
    function runPreview() {
        var file = selectedFile();

        if (!file) {
            esNotify('warning', 'Choose a file', 'Select the .xlsx export first.');
            return;
        }
        if (fileTooLarge(file)) {
            esNotify('warning', 'File too large', 'That file is larger than the allowed size.');
            return;
        }

        var $btn = $('#btn_preview').prop('disabled', true);

        post('<?= base_url("students/import/preview") ?>', buildFormData(), function (data) {
            lastPreview = data;
            renderMappings(data);
            renderPreview(data);
            $('#mapping_card, #preview_card').show();
            $('#result_card').hide();
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    function renderMappings(data) {
        var $boards = $('#board_map_table tbody').empty();

        $.each(data.boards, function (_, board) {
            var $row = $('<tr>');
            $row.append($('<td>').text(board.source || '(blank)')
                .append(board.type ? $('<span class="badge badge-glass-indigo ms-2">').text(board.type) : ''));
            $row.append($('<td class="text-center">').text(board.rows));

            var $select = $('<select class="form-select select2-ajax-college">')
                .attr('data-source', board.source)
                .append($('<option value="">').text('-- choose university --'));

            if (board.target) {
                $select.append($('<option>').attr('value', board.target).prop('selected', true)
                    .text(board.target_label || ('#' + board.target)));
            }

            var $cell = $('<td>').append($select);
            if (board.auto) {
                $cell.append($('<div class="small text-muted mt-1">').text('matched automatically by name'));
            }
            $row.append($cell);
            $boards.append($row);
        });

        var $courses = $('#course_map_table tbody').empty();

        $.each(data.courses, function (_, course) {
            var $row = $('<tr>');
            $row.append($('<td>').text(course.source || '(blank)'));
            $row.append($('<td class="text-center">').text(course.rows));

            var $select = $('<select class="form-select select2-ajax-stream">')
                .attr('data-source', course.source)
                .append($('<option value="">').text('-- choose stream --'));

            if (course.target) {
                $select.append($('<option>').attr('value', course.target).prop('selected', true).text(course.target));
            }

            $row.append($('<td>').append($select));
            $courses.append($row);
        });

        // Same AJAX pickers the New Entry form uses, so the vocabulary offered
        // here is exactly the vocabulary manual entry offers.
        esAjaxSelect('#board_map_table .select2-ajax-college', '<?= base_url("api/colleges") ?>', {
            context: 'Loading universities failed'
        });
        esAjaxSelect('#course_map_table .select2-ajax-stream', '<?= base_url("api/streams") ?>', {
            context: 'Loading streams failed'
        });
    }

    function statusBadge(status) {
        var map = {
            ready:       ['badge-glass-emerald', 'Ready'],
            warning:     ['badge-glass-amber',   'Warning'],
            record_only: ['badge-glass-indigo',  'Record only'],
            error:       ['badge-glass-rose',    'Error']
        };
        var spec = map[status] || map.error;
        return '<span class="badge ' + spec[0] + '">' + spec[1] + '</span>';
    }

    function renderPreview(data) {
        var $body = $('#preview_table tbody').empty();

        $.each(data.rows, function (_, row) {
            $body.append(
                '<tr>' +
                    '<td>' + row.line + '</td>' +
                    '<td>' + statusBadge(row.status) + '</td>' +
                    '<td class="fw-bold text-dark">' + esEscapeHtml(row.name || '-') + '</td>' +
                    '<td><span class="badge badge-glass-indigo">' + esEscapeHtml(row.case_no || '-') + '</span></td>' +
                    '<td class="small text-muted">' + esEscapeHtml(row.board || '-') + '</td>' +
                    '<td class="small text-muted">' + esEscapeHtml((row.messages || []).join(' ')) + '</td>' +
                '</tr>'
            );
        });

        var c = data.counts || {};
        $('#count_ready').text((c.ready || 0) + ' ready');
        $('#count_warning').text((c.warning || 0) + ' warnings');
        $('#count_record').text((c.record_only || 0) + ' record only');
        $('#count_error').text((c.error || 0) + ' errors');

        $('#preview_summary').text(
            'Sheet "' + (data.sheet || '') + '" — ' + (c.total || 0) + ' rows, ' +
            data.boards.length + ' certifying bodies, ' + data.courses.length + ' courses.'
        );

        var dispatchable = (c.ready || 0) + (c.warning || 0);
        $('#commit_label').text(
            dispatchable > 0
                ? 'Import ' + (c.total - (c.error || 0)) + ' record(s) and dispatch ' + dispatchable
                : 'Import ' + (c.total - (c.error || 0)) + ' record(s)'
        );
        $('#btn_commit').prop('disabled', (c.total - (c.error || 0)) <= 0);

        if (data.oversized && data.oversized.length) {
            $('#oversized_notice').show().text(
                'These certifying bodies have more candidates than one dispatch batch allows (' +
                data.max_batch + '): ' + data.oversized.join('; ') +
                '. Split the file before importing.'
            );
            $('#btn_commit').prop('disabled', true);
        } else {
            $('#oversized_notice').hide();
        }
    }

    // --- Commit ----------------------------------------------------------
    $('#btn_commit').on('click', function () {
        if (!lastPreview) { return; }

        var proceed = function () {
            var $btn = $('#btn_commit').prop('disabled', true);

            post('<?= base_url("students/import/commit") ?>', buildFormData(), function (data) {
                renderResult(data);
                $('#preview_card, #mapping_card').hide();
                $('#result_card').show();
                esNotify('success', 'Import complete', data.recorded + ' record(s) saved.');
            }).always(function () {
                $btn.prop('disabled', false);
            });
        };

        if (window.Swal) {
            Swal.fire({
                icon: 'question',
                title: 'Import these candidates?',
                text: 'This saves the admission records and creates the dispatch batches. It cannot be undone from this screen.',
                showCancelButton: true,
                confirmButtonText: 'Yes, import'
            }).then(function (r) { if (r.isConfirmed) { proceed(); } });
        } else {
            proceed();
        }
    });

    function renderResult(data) {
        $('#result_summary').text(
            data.recorded + ' admission record(s) saved (' + data.updated + ' updated), ' +
            data.batches.length + ' dispatch batch(es) created, ' +
            data.not_dispatched + ' recorded but not dispatched, ' +
            data.skipped + ' skipped. Reference ' + data.import_ref + '.'
        );

        var $body = $('#result_table tbody').empty();

        $.each(data.batches, function (_, batch) {
            var uni = '<?= base_url("pdf/dispatch/") ?>' + encodeURIComponent(batch.array_space);
            var acc = '<?= base_url("pdf/dispatchAccounts/") ?>' + encodeURIComponent(batch.array_space);

            $body.append(
                '<tr>' +
                    '<td><a href="<?= base_url("students/batch/") ?>' + encodeURIComponent(batch.array_space) + '">' +
                        esEscapeHtml(batch.array_space) + '</a></td>' +
                    '<td>' + esEscapeHtml(batch.university) + '</td>' +
                    '<td class="text-center"><span class="badge badge-glass-indigo">' + batch.count + '</span></td>' +
                    // Both open pdf/dispatch*, which require students.print. An
                    // importer without it would get a new tab bounced to the
                    // dashboard, so the row shows the batch link only. Emitted
                    // from PHP because JS has no view of the session's grants.
                    '<td class="text-end">' +
<?php if (can('students.print')): ?>
                        '<a class="btn btn-sm btn-glass text-primary" target="_blank" href="' + uni + '">Uni View</a> ' +
                        '<a class="btn btn-sm btn-glass text-emerald" target="_blank" href="' + acc + '">AC View</a>' +
<?php endif; ?>
                    '</td>' +
                '</tr>'
            );
        });
    }

    $('#btn_preview').on('click', runPreview);
    $('#btn_reapply').on('click', runPreview);

    // Choosing a different file invalidates the preview on screen.
    $('#import_file').on('change', function () {
        lastPreview = null;
        $('#mapping_card, #preview_card, #result_card').hide();
    });
});
</script>
