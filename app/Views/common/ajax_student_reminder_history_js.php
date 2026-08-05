<script>
$(document).ready(function () {
    $('.delete-student-rem-form').on('submit', function (e) {
        var form = this;

        if (form.dataset.esConfirmed === '1' || !window.Swal) {
            return true;
        }

        e.preventDefault();

        Swal.fire({
            title: 'Delete this reminder?',
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
