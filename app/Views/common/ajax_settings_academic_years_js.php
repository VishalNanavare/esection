<script>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    $('.edit-year-btn').on('click', function () {
        var id = $(this).data('id');

        $.ajax({
            url: '<?= base_url("settings/academic-years/getJson/") ?>' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data) {
                esNotify('error', 'Could not load academic year', (res && res.message) || 'Unexpected response.');
                return;
            }

            var data = res.data;
            $('#edit_year_label').val(data.year_label);
            $('#edit_start_date').val(data.start_date);
            $('#edit_end_date').val(data.end_date);
            $('#edit_is_current').prop('checked', String(data.is_current) === '1');
            $('#edit_year_form').attr('action', '<?= base_url("settings/academic-years/update/") ?>' + id);
            $('#editYearModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the academic year record');
        });
    });

    // --- Delete confirmation -------------------------------------------------
    $('.delete-year-form').on('submit', function (e) {
        var form = this;

        if (form.dataset.esConfirmed === '1' || !window.Swal) {
            return true;
        }

        e.preventDefault();

        Swal.fire({
            title: 'Delete this academic year?',
            text: (form.dataset.name || 'This academic year') + ' will be permanently removed.',
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
