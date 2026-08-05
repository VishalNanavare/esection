<script>
$(document).ready(function () {
    // A backup takes seconds to run. Without feedback the button looks dead
    // and gets clicked again, which only hits BackupService's "already
    // running" lock -- so disable it and say what is happening instead.
    $('.backup-run-form').on('submit', function () {
        var $btn = $(this).find('button[type="submit"]');

        $btn.prop('disabled', true)
            .html('<i class="fa fa-spinner fa-spin me-1"></i> Working, please wait...');
    });
});
</script>
