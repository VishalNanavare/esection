<script>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    // Delegated on document: these buttons live inside #user_rows, which is
    // replaced wholesale on every AJAX refresh. A direct binding would stop
    // firing after the first save and the Edit buttons would silently die.
    $(document).on('click', '.edit-user-btn', function () {
        var id = $(this).data('id');

        $.ajax({
            url: '<?= base_url("settings/users/getJson/") ?>' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data) {
                esNotify('error', 'Could not load user', (res && res.message) || 'Unexpected response.');
                return;
            }

            var data = res.data;
            $('#edit_username').val(data.username);
            $('#edit_password').val('');
            $('#edit_full_name').val(data.full_name);
            $('#edit_email').val(data.email);
            $('#edit_role').val(data.role);

            var grantedPages = data.pages || [];
            $('.edit-page-checkbox').each(function () {
                $(this).prop('checked', grantedPages.indexOf($(this).val()) !== -1);
            });

            $('#edit_user_form').attr('action', '<?= base_url("settings/users/update/") ?>' + id);
            $('#editUserModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the user record');
        });
    });
});
</script>
