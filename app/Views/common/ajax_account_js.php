<script>
$(document).ready(function () {

    // Client-side half of the Reset Password validation. The authoritative
    // checks live in UserManagementService::assertPasswordPolicy() and
    // changeOwnPassword() -- everything here is only to spare the operator a
    // round trip, and none of it is trusted by the server.
    //
    // Length is handled by the minlength/maxlength attributes in the layout, so
    // the browser reports those itself. What it cannot express is "these two
    // fields must match", which is what setCustomValidity is for: it blocks the
    // native submit, so the shared js-ajax handler never even fires.

    var $form    = $('#reset_password_form');
    var $newPw   = $('#rp_new');
    var $confirm = $('#rp_confirm');

    if ($form.length === 0) {
        return;
    }

    function checkMatch() {
        var mismatch = $newPw.val() !== '' && $confirm.val() !== '' && $newPw.val() !== $confirm.val();

        // An empty string is what marks a field valid; any other value marks it
        // invalid and becomes the browser's own message.
        $confirm[0].setCustomValidity(mismatch ? 'The two new passwords do not match.' : '');
        $confirm.toggleClass('is-invalid', mismatch);

        return !mismatch;
    }

    $newPw.on('input', checkMatch);
    $confirm.on('input', checkMatch);

    // Re-check on submit as well: a password manager can fill both boxes
    // without firing `input`, which would otherwise leave a stale verdict.
    $form.on('submit', checkMatch);

    // Clear only when the operator actually dismissed the dialog themselves --
    // never as a side effect of an error being shown.
    //
    // SweetAlert and a Bootstrap modal both trap focus. When the server rejects
    // a change ("Your current password is not correct.") the alert opens over
    // the still-open modal, and dismissing it hands focus back in a way that
    // can close the modal underneath. This handler then wiped all three boxes,
    // so the operator lost their typed current password too and had to start
    // over -- punishing them for a single mistyped character.
    //
    // A reset is still wanted when they close the dialog deliberately, so the
    // next open is clean. The two are told apart by a flag set on the paths
    // that legitimately close it.
    var closingAfterSuccess = false;

    $form.on('es:reset-clean', function () { closingAfterSuccess = true; });

    $('#resetPasswordModal')
        .on('hide.bs.modal', function () {
            // A dismiss while our own error dialog is on screen is the focus-trap
            // bounce described above, not an intentional close.
            if (document.querySelector('.swal2-container')) {
                closingAfterSuccess = false;
            }
        })
        .on('hidden.bs.modal', function () {
            if (closingAfterSuccess || !document.querySelector('.swal2-container')) {
                $form[0].reset();
            }
            closingAfterSuccess = false;

            // The validity flag is always safe to clear: it is recomputed from
            // the field values on the next keystroke.
            $confirm[0].setCustomValidity('');
            $confirm.removeClass('is-invalid');
        });
});
</script>
