<script>
$(document).ready(function() {
    // Initialize AJAX Select2 for College Search
    $('.select2-ajax-college').select2({
        width: '100%',
        ajax: {
            url: '<?= base_url("api/colleges") ?>',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term, page: params.page || 1 };
            },
            processResults: function(data) {
                return { results: data.results, pagination: data.pagination };
            }
        }
    });

    // Initialize AJAX Select2 for Stream Search
    $('.select2-ajax-stream').select2({
        width: '100%',
        ajax: {
            url: '<?= base_url("api/streams") ?>',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return { results: data.results };
            }
        }
    });

    var studentList = [];

    // AJAX Auto-fill College Information on Select2 Change
    $('#clg_add_select').on('change', function() {
        var colId = $(this).val();
        if (!colId) return;

        $.ajax({
            url: '<?= base_url("students/getCollegeInfo/") ?>' + colId,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    $('#clg_add').val(res.address);
                    $('#In_Favour_of').val(res.in_favour_of);
                    $('#to').val(res.head_name || 'The Controller of Examinations');
                }
            }
        });
    });

    // Add Student Row
    $('#btn_add_student').on('click', function() {
        var name = $('#stud_name').val().trim();
        var neeName = $('#stud_nee_name').val().trim() || '-';
        var caseNo = $('#eligibility_case_no').val().trim();
        var verifyByYou = $('#verification_by_you').val().trim() || 'Marksheet Verification';

        if (!name || !caseNo) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Required Fields',
                text: 'Please enter Candidate Full Name and Eligibility Case Number.',
                confirmButtonText: 'OK'
            });
            return;
        }

        studentList.push({
            student_name: name,
            student_nee_name: neeName,
            eligibility_case_no: caseNo,
            verification_by_you: verifyByYou
        });

        renderStudentTable();

        // Clear entry inputs
        $('#stud_name').val('');
        $('#stud_nee_name').val('');
        $('#eligibility_case_no').val('');
        $('#verification_by_you').val('');

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Candidate added to list',
            showConfirmButton: false,
            timer: 2000
        });
    });

    function renderStudentTable() {
        var tbody = $('#student_batch_table tbody');
        tbody.empty();

        if (studentList.length === 0) {
            tbody.append('<tr id="empty_row"><td colspan="6" class="text-center text-muted py-4">No candidates added to this batch yet.</td></tr>');
            return;
        }

        $.each(studentList, function(idx, item) {
            var row = `<tr>
                <td>${idx + 1}</td>
                <td class="fw-bold text-dark">${item.student_name}</td>
                <td>${item.student_nee_name}</td>
                <td><span class="badge badge-glass-indigo">${item.eligibility_case_no}</span></td>
                <td>${item.verification_by_you}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-glass text-danger remove-row" data-index="${idx}">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>`;
            tbody.append(row);
        });
    }

    $(document).on('click', '.remove-row', function() {
        var idx = $(this).data('index');
        studentList.splice(idx, 1);
        renderStudentTable();
    });

    // Save and Render PDF Dispatch
    $('#btn_save_and_pdf').on('click', function() {
        if (studentList.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Empty Candidate Batch',
                text: 'Please add at least one candidate before saving.',
                confirmButtonText: 'OK'
            });
            return;
        }

        var payload = {
            common_no: $('#display_common_no').text(),
            to_name: $('#to').val(),
            clg_add: $('#clg_add').val(),
            admission_taken_year: $('#admission_taken_year').val(),
            admission_taken_in: $('#admission_taken_in').val(),
            in_favour_of: $('#In_Favour_of').val(),
            students: studentList
        };

        Swal.fire({
            title: 'Saving Verification Batch...',
            text: 'Please wait while records are saved and PDF dispatch letter is generated.',
            allowOutsideClick: false,
            didOpen: function() {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '<?= base_url("students/storeBatch") ?>',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            headers: {
                'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
            },
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Verification Batch Saved',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(function() {
                        window.open(res.redirect_url, '_blank');
                        window.location.href = '<?= base_url("dashboard") ?>';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Save Failed',
                        text: res.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Server Error',
                    text: 'A server error occurred while processing your request.'
                });
            }
        });
    });
});
</script>
