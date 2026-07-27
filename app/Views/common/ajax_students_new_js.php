<script>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-college', '<?= base_url("api/colleges") ?>', {
        context: 'Loading universities failed'
    });

    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
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

        studentList.push({
            student_name: name,
            student_nee_name: neeName,
            eligibility_case_no: caseNo,
            verification_by_you: verifyByYou
        });

        renderStudentTable();

        $('#stud_name, #stud_nee_name, #eligibility_case_no, #verification_by_you').val('');
        $('#stud_name').trigger('focus');

        esNotify('success', 'Candidate added to the batch');
    });

    function renderStudentTable() {
        var tbody = $('#student_batch_table tbody');
        tbody.empty();

        $('#batch_count').text(studentList.length);

        if (studentList.length === 0) {
            tbody.append(
                '<tr id="empty_row"><td colspan="6" class="text-center text-muted py-4">' +
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
