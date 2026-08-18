<script {csp-script-nonce}>
$(document).ready(function () {

    // Every POST on this screen goes through AJAX so the page never reloads.
    // The server returns the re-rendered history rows with each reply, so the
    // table is refreshed from the SAME partial the page was built with rather
    // than being rebuilt in JavaScript -- the two cannot drift.
    //
    // The forms keep their real action/method attributes. If this script fails
    // to load, every one of them still submits normally and the controller
    // still answers with a redirect and a flash message.
    //
    // The submit/dialog plumbing itself now lives in ajax_common_js.php and is
    // shared with every other screen. What stays here is the part that is
    // genuinely specific to Backup: applyResponse(). It is passed in as
    // onResponse rather than being closed over by the shared helper, because
    // the shared helper has no business knowing about a password badge.

    /** Repaint the table and the bits of the panel that a POST can change. */
    function applyResponse(res) {
        if (res.html !== undefined) {
            $('#backup_history_rows').html(res.html);
        }

        if (!res.state) {
            return;
        }

        // The "Configured / Not configured" badge and the setup banner both
        // hang off this, and saving a password flips it.
        if (res.state.password_configured) {
            $('#backup_password_badge')
                .removeClass('badge-glass-amber').addClass('badge-glass-emerald')
                .html('<i class="fa fa-check-circle me-1"></i> Configured');
            $('#backup_setup_notice').slideUp(150);
            $('#backup_password_submit').text('Change password');
        }

        // Setting a password unblocks "Backup now" -- without this the button
        // stays disabled until a manual reload, which is exactly the reload we
        // are trying to avoid.
        $('#btn_run_sql').prop('disabled', !res.state.can_run_sql);

        if (res.state.retention_count !== undefined) {
            $('input[name="backup_retention_count"]').val(res.state.retention_count);
        }
    }

    /** Local wrapper so every call site on this screen repaints and is titled alike. */
    function submitBackupForm($form, busy, extra) {
        return esSubmitForm($form, $.extend({
            title: 'Backup',
            busy: busy,
            context: 'The backup action could not be completed',
            onResponse: applyResponse
        }, extra || {}));
    }

    // --- Run a backup ---------------------------------------------------
    // A SQL dump takes seconds. Without feedback the button looks dead and
    // gets clicked again, which only hits BackupService's "already running"
    // lock -- so it is disabled and labelled for the duration.
    $('.backup-run-form').on('submit', function (e) {
        e.preventDefault();

        var isSql = $(this).attr('action').indexOf('/sql') !== -1;

        submitBackupForm($(this), {
            button: 'Working...',
            title:  isSql ? 'Creating the system backup...' : 'Exporting reference data...',
            text:   isSql
                ? 'Dumping every table and packaging it into a password-protected file. This can take a few moments -- please do not close this page.'
                : 'Writing one sheet per table. This can take a few moments.'
        });
    });

    // --- Password / retention -------------------------------------------
    $('.backup-settings-form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);

        submitBackupForm($form, { button: 'Saving...', title: 'Saving...', text: '' }, {
            onSuccess: function () {
                // Clear the password fields on success so the values are not
                // left sitting in the DOM after the save.
                if ($form.data('clear-on-success')) {
                    $form.find('input[type="password"]').val('');
                }
            }
        });
    });

    // --- Delete ----------------------------------------------------------
    // Delegated: these rows are replaced wholesale on every refresh, so a
    // handler bound directly to the forms would stop firing after the first
    // AJAX update.
    $('#backup_history_rows').on('submit', '.js-delete-backup', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var filename = $form.data('filename') || 'This backup';

        var doDelete = function () {
            submitBackupForm($form, {
                button: 'Deleting...',
                title:  'Deleting backup...',
                text:   'Removing the file from the server.'
            });
        };

        if (!window.Swal) {
            doDelete();
            return;
        }

        Swal.fire({
            title: 'Delete this backup permanently?',
            text: filename + ' will be removed from the server and cannot be recovered.',
            icon: 'warning',
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result && result.isConfirmed) {
                doDelete();
            }
        });
    });
});
</script>
