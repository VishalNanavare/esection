<script {csp-script-nonce}>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-state', '<?= base_url("api/states") ?>', {
        context: 'Loading states failed'
    });
    esAjaxSelect('.select2-ajax-academic-year', '<?= base_url("api/academic-years") ?>', {
        context: 'Loading academic years failed'
    });
    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    // --- Send ---------------------------------------------------------------
    // The confirmation, the busy state and the send itself are all declared on
    // the form now (bulk_email/index.php) and driven by the shared handler.
    //
    // Worth recording why the old code had to go rather than be adapted. It
    // ended in `form.submit()`, which dispatches no submit event -- so its own
    // re-entrant guard could never fire either. The consequence was a
    // pre-existing dead branch: the "Sending, please wait..." spinner at the top
    // of that handler was unreachable whenever SweetAlert2 was present, which is
    // always. The Send button therefore stayed enabled and unlabelled for the
    // whole send, on the one screen in the app where a second click re-mails up
    // to 500 external recipients. It is now disabled for the duration by
    // esSubmitForm, behind a non-dismissible dialog.
});
</script>
