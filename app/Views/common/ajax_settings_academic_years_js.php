<script {csp-script-nonce}>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    // Delegated: these buttons live inside #academic_year_rows, which is
    // replaced wholesale on every AJAX refresh.
    $(document).on('click', '.edit-year-btn', function () {
        var id = $(this).data('id');

        $.ajax({
            url: '<?= base_url("settings/academic-years/getJson/") ?>' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data) {
                esNotify('error', 'Could not load academic year', (res && res.message) || 'Unexpected response.');
                return;
            }

            var data = res.data;
            $('#edit_year_label').val(data.year_label);
            $('#edit_is_current').prop('checked', String(data.is_current) === '1');
            $('#edit_year_form').attr('action', '<?= base_url("settings/academic-years/update/") ?>' + id);
            $('#editYearModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the academic year record');
        });
    });

    // --- Delete confirmation -------------------------------------------------
    // Deliberately NOT bound here any more. It is declared on the form itself
    // (data-confirm-title / data-confirm-text in settings/_academic_years_rows.php)
    // and handled by the shared delegated handler in ajax_common_js.php.
    //
    // The binding that used to live here ended in `form.submit()`. jQuery has no
    // special-event hook for submit and does not patch
    // HTMLFormElement.prototype.submit, so the native DOM submit() dispatches NO
    // submit event -- meaning a delegated AJAX handler could never observe it.
    // Keeping both would have been worse than either alone: the direct handler's
    // preventDefault() does not stop propagation, so the delegated handler would
    // have sent the delete while the "Are you sure?" dialog was still on screen,
    // and Cancel would have done nothing to the row that was already gone.
});
</script>
