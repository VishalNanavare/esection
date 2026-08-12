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

    // --- Delete confirm -------------------------------------------------------
    // Same shape as the candidate-delete confirm, with stronger wording: this
    // destroys a recovery point, and the SQL archive is the only copy of the
    // database outside the live server. focusCancel so the dangerous button is
    // never the one that fires on a stray Enter.
    //
    // The dataset flag lets the real submit through on the second pass; the
    // !window.Swal fallback means the form still works if SweetAlert failed to
    // load, rather than the Delete button silently doing nothing.
    $('.js-delete-backup').on('submit', function (e) {
        var form = this;

        if (form.dataset.esConfirmed === '1' || !window.Swal) {
            return true;
        }

        e.preventDefault();

        Swal.fire({
            title: 'Delete this backup permanently?',
            text: (form.dataset.filename || 'This backup')
                + ' will be removed from the server and cannot be recovered.',
            icon: 'warning',
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: 'Yes, delete it',
            cancelButtonText: 'Cancel'
        }).then(function (result) {
            if (result && result.isConfirmed) {
                form.dataset.esConfirmed = '1';
                form.submit();
            }
        });
    });
});
</script>
