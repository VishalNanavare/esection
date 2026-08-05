<script>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    $('.edit-student-btn').on('click', function () {
        var id = $(this).data('id');

        $.ajax({
            url: '<?= base_url("students/getJson/") ?>' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data) {
                esNotify('error', 'Could not load candidate', (res && res.message) || 'Unexpected response.');
                return;
            }

            var data = res.data;
            $('#edit_student_name').val(data.student_name);
            $('#edit_student_nee_name').val(data.student_nee_name);
            $('#edit_eligibility_case_no').val(data.eligibility_case_no);
            $('#edit_verification').val(data.verification_of_marksheet_done_by_you);
            $('#edit_student_email').val(data.email);
            $('#edit_student_form').attr('action', '<?= base_url("students/update/") ?>' + id);
            $('#editStudentModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the candidate record');
        });
    });

    // --- Delete confirm -------------------------------------------------------
    $('.delete-student-form').on('submit', function (e) {
        var form = this;

        if (form.dataset.esConfirmed === '1' || !window.Swal) {
            return true;
        }

        e.preventDefault();

        Swal.fire({
            title: 'Delete this candidate permanently?',
            text: (form.dataset.name || 'This record') + ' will be permanently removed. This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result && result.isConfirmed) {
                form.dataset.esConfirmed = '1';
                form.submit();
            }
        });
    });
});
</script>
