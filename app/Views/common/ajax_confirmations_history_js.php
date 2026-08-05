<script>
$(document).ready(function () {
    // Deep-link highlight from the "Already confirmed" link on
    // confirmations/index.php -- scroll the specific student's row into
    // view among their batch-mates on confirmations/batch/{id}.
    var $highlighted = $('.confirmation-row-highlight');
    if ($highlighted.length) {
        $highlighted[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // History-page filter dropdowns. No-ops on batch_detail.php, which
    // includes this same file but has none of these elements.
    esAjaxSelect('.select2-ajax-academic-year', '<?= base_url("api/academic-years") ?>', {
        context: 'Loading academic years failed'
    });

    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    // History filters persist the university NAME, not its numeric id --
    // same remap reminders/university.php's college picker already uses.
    esAjaxSelect('.select2-ajax-college', '<?= base_url("api/colleges") ?>', {
        context: 'Loading universities failed',
        mapItem: function (item) {
            return { id: item.name, text: item.text };
        }
    });

    $('.delete-confirmation-form').on('submit', function (e) {
        var form = this;

        if (form.dataset.esConfirmed === '1' || !window.Swal) {
            return true;
        }

        e.preventDefault();

        Swal.fire({
            title: 'Delete this confirmation record?',
            text: (form.dataset.name || 'This record') + ' will be permanently removed.',
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
