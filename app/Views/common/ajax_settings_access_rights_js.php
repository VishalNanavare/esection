<script>
$(document).ready(function () {
    $('#access_rights_form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn  = $('#access_rights_submit_btn').prop('disabled', true);

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json'
        }).done(function (res) {
            $btn.prop('disabled', false);

            if (!res || res.status !== 'success') {
                esNotify('error', 'Could not save', (res && res.message) || 'Please try again.');
                return;
            }

            esNotify('success', 'Access rights updated');
        }).fail(function (xhr) {
            $btn.prop('disabled', false);
            esAjaxError(xhr, 'Access rights could not be saved');
        });
    });
});
</script>
