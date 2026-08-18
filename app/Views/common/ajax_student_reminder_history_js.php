<script {csp-script-nonce}>
$(document).ready(function () {
    // --- Delete confirm -------------------------------------------------------
    // Declared on the form itself (reminders/_student_history_rows.php) and
    // handled by the shared delegated handler in ajax_common_js.php.
    //
    // The binding that used to live here ended in `form.submit()`, which
    // dispatches NO submit event, so a delegated AJAX handler could never have
    // seen it. Running both would have been worse than either: preventDefault()
    // does not stop propagation, so the reminder would have been deleted while
    // the confirmation dialog was still waiting for an answer.
});
</script>
