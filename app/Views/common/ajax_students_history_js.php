<script {csp-script-nonce}>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    // Delegated: these buttons live inside #batch_student_rows, which is
    // replaced wholesale on every AJAX refresh.
    $(document).on('click', '.edit-student-btn', function () {
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
    // Declared on the form itself now (students/_batch_rows.php), handled by the
    // shared delegated handler. The binding that used to live here ended in
    // `form.submit()`, which dispatches NO submit event -- so a delegated AJAX
    // handler could never have seen it, and running both would have deleted the
    // candidate while "Are you sure?" was still on screen.

    // --- Leaving an emptied batch --------------------------------------------
    // Deleting the last candidate leaves this page describing a batch that no
    // longer exists: batchDetail() has no emptiness guard, and from that point
    // the Uni View / AC View buttons return a bare text string instead of a PDF.
    // A full reload used to hide this; without one it would be plainly visible,
    // so leave for the history list instead.
    $(document).on('es:refreshed', function (e, $form, res) {
        if (res && res.remaining === 0) {
            esNotify('info', 'Batch empty', 'That was the last candidate -- returning to history.');
            window.setTimeout(function () {
                window.location.href = '<?= base_url("students/history") ?>';
            }, 1200);
        }
    });
});
</script>
