<script>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    $('.edit-reg-btn').on('click', function () {
        var id = $(this).data('id');

        $.ajax({
            url: '<?= base_url("regularization/getJson/") ?>' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data) {
                esNotify('error', 'Could not load record', (res && res.message) || 'Unexpected response.');
                return;
            }

            var data = res.data;
            $('#edit_reg_gender').val(data.gender);
            $('#edit_reg_student_name').val(data.student_name);
            $('#edit_reg_eligibility_case_no').val(data.eligibility_case_no);
            $('#edit_reg_admission_letter_for').val(data.admission_letter_for);
            $('#edit_reg_admission_letter_date').val(data.admission_letter_date);
            $('#edit_reg_admission_taken_year').val(data.admission_taken_year);
            $('#edit_reg_admission_taken_in').val(data.admission_taken_in);
            $('#edit_reg_clg_add').val(data.university_name);
            $('#edit_reg_passing_course').val(data.passing_course);
            $('#edit_reg_form').attr('action', '<?= base_url("regularization/update/") ?>' + id);
            $('#editRegModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the regularization record');
        });
    });

    // --- Delete confirm -------------------------------------------------------
    $('.delete-reg-form').on('submit', function (e) {
        var form = this;

        if (form.dataset.esConfirmed === '1' || !window.Swal) {
            return true;
        }

        e.preventDefault();

        Swal.fire({
            title: 'Delete this letter permanently?',
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
