<script {csp-script-nonce}>
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

            // Scoped to the EDIT card set. The Add and Edit modals both render
            // the same partial and are both in the DOM at once, so an unscoped
            // selector would silently rewrite the Add form's ticks as well.
            var grantedPages = data.pages || [];
            var $editPerms   = $('[data-perm-scope="edit"]');

            $editPerms.find('.js-perm-check').each(function () {
                $(this).prop('disabled', false)
                       .prop('checked', grantedPages.indexOf($(this).val()) !== -1);
            });

            // Re-runs the view-implication and the "N selected" badge against
            // the values just loaded.
            $(document).trigger('es:permissions-rendered');

            $('#edit_user_form').attr('action', '<?= base_url("settings/users/update/") ?>' + id);
            $('#editUserModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the user record');
        });
    });
});
</script>
