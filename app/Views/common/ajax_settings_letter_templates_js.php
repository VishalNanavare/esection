<script>
$(document).ready(function () {
    // --- Footer department (plain text, no files, no preview needed) --------
    $('#footer_form').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn  = $form.find('button[type=submit]').prop('disabled', true);

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
            esNotify('success', 'Department name updated');
        }).fail(function (xhr) {
            $btn.prop('disabled', false);
            esAjaxError(xhr, 'The department name could not be saved');
        });
    });

    // --- Per-template Save button ---------------------------------------------
    // Bound to the SAVE button's own click, deliberately NOT the form's
    // submit event: each form also has a "Preview PDF" button that uses
    // formaction/formtarget to open a real new tab with a live PDF built
    // from the current (unsaved) field values. That button has no handler
    // at all here -- it must remain a genuine submit, not be intercepted.
    $('.letter-save-btn').on('click', function (e) {
        e.preventDefault();

        var $btn  = $(this).prop('disabled', true);
        var $form = $btn.closest('form');

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
            esNotify('success', 'Letter template updated');
        }).fail(function (xhr) {
            $btn.prop('disabled', false);
            esAjaxError(xhr, 'The letter template could not be saved');
        });
    });
});
</script>
