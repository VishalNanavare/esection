<script {csp-script-nonce}>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    // Delegated: these buttons live inside #regularization_rows.
    $(document).on('click', '.edit-reg-btn', function () {
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
    // Declared on the form itself now, handled by the shared delegated handler
    // in ajax_common_js.php. The binding that used to live here ended in
    // `form.submit()`, which dispatches NO submit event (jQuery has no
    // special-event hook for submit and does not patch
    // HTMLFormElement.prototype.submit) -- so a delegated AJAX handler could
    // never have observed it, and keeping both would have sent the delete while
    // the "Are you sure?" dialog was still on screen.
});
</script>
