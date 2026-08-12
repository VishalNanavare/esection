<script>
$(document).ready(function () {

    // Every POST on this screen goes through AJAX so the page never reloads.
    // The server returns the re-rendered history rows with each reply, so the
    // table is refreshed from the SAME partial the page was built with rather
    // than being rebuilt in JavaScript -- the two cannot drift.
    //
    // The forms keep their real action/method attributes. If this script fails
    // to load, every one of them still submits normally and the controller
    // still answers with a redirect and a flash message.

    var CSRF = '<?= csrf_hash() ?>';

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

    /**
     * @param {jQuery} $form
     * @param {string} busyLabel  shown on the button while the request runs
     */
    function submitViaAjax($form, busyLabel) {
        var $btn     = $form.find('button[type="submit"]');
        var original = $btn.html();

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> ' + busyLabel);

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            // Marks the request as AJAX for CodeIgniter's isAJAX(), which is
            // what makes the controller answer with JSON instead of a redirect.
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF }
        }).done(function (res) {
            applyResponse(res);
            esNotify('success', 'Backup', res.message || 'Done.');

            // Clear the password fields on success so the values are not left
            // sitting in the DOM after the save.
            if ($form.data('clear-on-success')) {
                $form.find('input[type="password"]').val('');
            }
        }).fail(function (xhr) {
            var res = xhr.responseJSON;

            if (res && res.message) {
                // A 422 is a real, expected outcome here (no password set, a
                // backup already running, validation) and its text is written
                // for the operator -- so show it, and still repaint, because
                // the table may have changed even on a failure.
                applyResponse(res);
                esNotify('warning', 'Backup', res.message);
            } else {
                esAjaxError(xhr, 'The backup action could not be completed');
            }
        }).always(function () {
            $btn.prop('disabled', false).html(original);
        });
    }

    // --- Run a backup ---------------------------------------------------
    // A SQL dump takes seconds. Without feedback the button looks dead and
    // gets clicked again, which only hits BackupService's "already running"
    // lock -- so it is disabled and labelled for the duration.
    $('.backup-run-form').on('submit', function (e) {
        e.preventDefault();
        submitViaAjax($(this), 'Working, please wait...');
    });

    // --- Password / retention -------------------------------------------
    $('.backup-settings-form').on('submit', function (e) {
        e.preventDefault();
        submitViaAjax($(this), 'Saving...');
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
            submitViaAjax($form, '');
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
