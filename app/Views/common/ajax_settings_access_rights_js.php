<script {csp-script-nonce}>
$(document).ready(function () {

    var CSRF = '<?= csrf_hash() ?>';

    // Whether the chips on screen have been touched since they were loaded or
    // last saved. Switching accounts replaces the whole card set, so without
    // this a click on another name silently threw away every unticked box --
    // no prompt, no notice, and the screen looked like a successful load.
    var dirty = false;

    $(document).on('change', '#access_rights_permissions .js-perm-check', function () {
        dirty = true;
    });

    // The three bulk buttons set state with .prop(), which fires no change
    // event -- so on the change handler alone, "Select all" followed by
    // clicking another name would have discarded 32 ticks without a prompt.
    // The toolbar pair sit outside #access_rights_permissions, hence the
    // separate selector.
    $(document).on('click', '#access_rights_permissions .js-perm-group-all, #access_rights_form .js-perm-all, #access_rights_form .js-perm-none', function () {
        dirty = true;
    });

    // --- Pick a staff account -------------------------------------------
    // Swaps the permission cards for the chosen user. The cards come back
    // rendered by the SAME partial the page was built with, so the checked
    // state and the markup cannot drift from what a full page load produces.
    $(document).on('click', '.js-staff-pick', function () {
        var $btn   = $(this);
        var userId = $btn.data('user-id');

        if ($btn.hasClass('is-selected')) {
            return;
        }

        if (dirty) {
            var current = $('#access_rights_username').text() || 'this account';

            if (window.Swal) {
                window.Swal.fire({
                    title: 'Discard unsaved changes?',
                    text: 'The permissions you changed for ' + current + ' have not been saved yet.',
                    icon: 'warning',
                    showCancelButton: true,
                    focusCancel: true,
                    confirmButtonText: 'Yes, discard them',
                    cancelButtonText: 'Keep editing'
                }).then(function (result) {
                    if (result && result.isConfirmed) {
                        dirty = false;
                        $btn.trigger('click');
                    }
                });
                return;
            }

            if (! window.confirm('Discard the unsaved permission changes for ' + current + '?')) {
                return;
            }
        }

        var $cards = $('#access_rights_permissions');

        // pointer-events, not just opacity. Dimming alone left every chip
        // clickable during the fetch, so ticks made mid-load were silently
        // overwritten by the incoming user's card set a moment later.
        $cards.css({ opacity: 0.4, 'pointer-events': 'none' });

        $.ajax({
            url: '<?= base_url("settings/access-rights/user/") ?>' + encodeURIComponent(userId),
            type: 'GET',
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).done(function (res) {
            if (!res || res.status !== 'success') {
                esNotify('error', 'Access Rights', (res && res.message) || 'Could not load that account.');
                return;
            }

            $('.js-staff-pick').removeClass('is-selected');
            $btn.addClass('is-selected');

            $cards.html(res.html);
            $('#access_rights_user_id').val(userId);
            $('#access_rights_username').text($btn.data('username'));

            // Re-run the view-implication and the counter against the new set.
            $(document).trigger('es:permissions-rendered');
            dirty = false;
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load that account');
        }).always(function () {
            $cards.css({ opacity: 1, 'pointer-events': '' });
        });
    });

    // --- Save -------------------------------------------------------------
    // Per-user only. The previous screen was a users x pages matrix saved in
    // one shot, which iterated EVERY staff account and wiped anyone whose row
    // was absent from the POST. With 32 permissions that matrix was unusable
    // anyway, and saving one account at a time removes the wipe hazard with it.
    $('#access_rights_form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn  = $('#access_rights_submit_btn');
        var html  = $btn.html();

        // Disabled boxes are not serialized, so an implied `view` would be
        // dropped from the payload. ajax_permissions_js re-enables them from a
        // CAPTURE-phase listener on document, which is the only way this
        // ordering is guaranteed: a bubbling handler there would run AFTER this
        // one (which is bound on the form itself), and serialize() below would
        // already have skipped the disabled boxes.
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving...');

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            $btn.prop('disabled', false).html(html);
            dirty = false;
            esNotify('success', 'Access Rights', (res && res.message) || 'Permissions updated.');
        }).fail(function (xhr) {
            $btn.prop('disabled', false).html(html);

            var res = xhr.responseJSON;

            if (xhr.status === 401 || xhr.status === 403) {
                esAjaxError(xhr, 'Permissions could not be saved');
                return;
            }

            if (res && res.message) {
                esBlockingError('Access Rights', res.message);
                return;
            }

            esAjaxError(xhr, 'Permissions could not be saved');
        });
    });
});
</script>
