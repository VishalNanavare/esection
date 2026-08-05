<script>
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

    // Sending is a real, sometimes slow, irreversible action -- confirm once
    // more (the count is right there on the button) and disable it so a
    // second click cannot queue the same batch twice.
    $('.bulk-send-form').on('submit', function (e) {
        var form = this;
        var count = form.dataset.count || '0';

        if (form.dataset.esConfirmed === '1') {
            $(form).find('button[type="submit"]')
                .prop('disabled', true)
                .html('<i class="fa fa-spinner fa-spin me-1"></i> Sending, please wait...');
            return true;
        }

        e.preventDefault();

        if (!window.Swal) {
            form.dataset.esConfirmed = '1';
            form.submit();
            return;
        }

        Swal.fire({
            title: 'Send ' + count + ' email(s)?',
            text: 'This cannot be undone. Every recipient shown in the list above will receive this message.',
            icon: 'warning',
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: 'Yes, send now',
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
