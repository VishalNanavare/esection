<script {csp-script-nonce}>
$(document).ready(function () {
    var $prefixInput = $('#case_no_prefix');
    var $preview = $('#preview_example');
    var currentYear = new Date().getFullYear();

    // Cosmetic only -- the real suffix is a random 4-digit number chosen
    // server-side by generate_case_no(); this just echoes the format live
    // as the admin types, ahead of the authoritative server-rendered value.
    $prefixInput.on('input', function () {
        var prefix = ($(this).val() || 'CASE').toUpperCase();
        $preview.val(prefix + '-' + currentYear + '/0000');
    });

    // --- Save -----------------------------------------------------------
    // Both fields have to be written back from the reply, which is why this
    // screen has its own handler rather than the shared js-ajax class:
    //
    //  - the prefix is uppercased server-side, so what was typed is not
    //    necessarily what was stored;
    //  - the preview is produced by previewNext() -> generate_case_no(), which
    //    picks a RANDOM four-digit suffix and re-rolls it against the database
    //    on every render. Leaving the field alone would show the typed-ahead
    //    placeholder ("...0000") as though it were the saved value.
    $('#numbering_form').on('submit', function (e) {
        e.preventDefault();

        esSubmitForm($(this), {
            title: 'Document Numbering',
            busy: { button: 'Saving...' },
            context: 'The numbering format could not be saved',
            onSuccess: function (res) {
                if (!res.state) {
                    return;
                }
                if (res.state.prefix !== undefined) {
                    $prefixInput.val(res.state.prefix);
                }
                if (res.state.preview !== undefined) {
                    $preview.val(res.state.preview);
                }
            }
        });
    });
});
</script>
