<script {csp-script-nonce}>
$(document).ready(function () {

    // --- SMTP settings ---------------------------------------------------
    // Own handler rather than the shared js-ajax class: saving flips two things
    // that are nowhere near the form -- the Status summary in the next card,
    // and the "saved" badge on the password label.
    $('#mail_smtp_form').on('submit', function (e) {
        e.preventDefault();

        esSubmitForm($(this), {
            title: 'Email settings',
            busy: { button: 'Saving...' },
            context: 'The email settings could not be saved',
            onSuccess: function (res) {
                if (res.html !== undefined) {
                    $('#mail_status_block').html(res.html);
                }

                if (res.state && res.state.password_configured) {
                    $('#mail_password_badge').html('<span class="badge badge-glass-emerald ms-1">saved</span>');
                    $('#mail_smtp_password').attr('placeholder', 'Leave blank to keep current');
                }

                // Never leave a submitted password sitting in the DOM. It is
                // also what the field means from here on: blank = keep current.
                $('#mail_smtp_password').val('');
            }
        });
    });

    // --- Test send -------------------------------------------------------
    // This one genuinely blocks. sendTest() calls set_time_limit(0) and talks
    // to a remote SMTP server, so against a slow or unreachable host it can sit
    // for a long time -- hence the dialog rather than just a disabled button.
    //
    // The previous handler only disabled the button and relied on the page
    // reload to put it back. With no reload that would have given the operator
    // exactly one test send per page load.
    $('.mail-test-form').on('submit', function (e) {
        e.preventDefault();

        esSubmitForm($(this), {
            title: 'Test email',
            busy: {
                button: 'Sending...',
                title:  'Sending the test email...',
                text:   'Connecting to the mail server. This can take a few moments if the server is slow to answer.'
            },
            context: 'The test email could not be sent'
        });
    });
});
</script>
