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

    // Reopening the dialog after a failure would otherwise still show the red
    // outline from the previous attempt.
    $('#resetPasswordModal').on('hidden.bs.modal', function () {
        $form[0].reset();
        $confirm[0].setCustomValidity('');
        $confirm.removeClass('is-invalid');
    });
});
</script>
