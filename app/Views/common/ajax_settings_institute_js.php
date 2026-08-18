<script {csp-script-nonce}>
$(document).ready(function () {
    var ALLOWED_TYPES = ['image/png', 'image/jpeg'];

    // --- Client-side file validation + instant preview ----------------------
    // Mirrors the backend's own mime/size/dimension checks in
    // InstituteDetailsService, so a bad file is rejected before it's ever
    // uploaded -- the backend check still runs too (defense in depth), this
    // is just faster feedback.
    function bindFileField(inputId, errorId, previewId) {
        var $input   = $('#' + inputId);
        var $error   = $('#' + errorId);
        var $preview = $('#' + previewId);

        $input.on('change', function () {
            var file = this.files && this.files[0];
            $error.hide().text('');

            if (!file) {
                return;
            }

            var maxSizeKb      = parseInt($input.data('max-size-kb'), 10) || 2048;
            var maxBytes       = maxSizeKb * 1024;
            var requiredWidth  = parseInt($input.data('required-width'), 10);
            var requiredHeight = parseInt($input.data('required-height'), 10);

            if (ALLOWED_TYPES.indexOf(file.type) === -1) {
                $error.text('Only PNG or JPEG images are accepted.').show();
                $input.val('');
                return;
            }

            if (file.size > maxBytes) {
                $error.text('This file is ' + (file.size / 1024 / 1024).toFixed(1) + 'MB. The limit is ' + (maxSizeKb / 1024) + 'MB.').show();
                $input.val('');
                return;
            }

            var reader = new FileReader();
            reader.onload = function (e) {
                var dataUrl = e.target.result;

                // Exact pixel-dimension check -- not a range. Loaded into a
                // throwaway Image() to read its natural size before it's
                // ever shown as the preview.
                var img = new Image();
                img.onload = function () {
                    if (requiredWidth && requiredHeight &&
                        (img.naturalWidth !== requiredWidth || img.naturalHeight !== requiredHeight)) {
                        $error.text('The image must be exactly ' + requiredWidth + 'x' + requiredHeight +
                            'px (this file is ' + img.naturalWidth + 'x' + img.naturalHeight + 'px).').show();
                        $input.val('');
                        return;
                    }
                    $preview.attr('src', dataUrl).show();
                };
                img.src = dataUrl;
            };
            reader.readAsDataURL(file);
        });
    }

    bindFileField('logo_input', 'logo_error', 'logo_preview');
    bindFileField('letterhead_input', 'letterhead_error', 'letterhead_preview');

    // --- Signature space (blank lines) validation ----------------------------
    // Mirrors InstituteDetailsService's own range check: blank is always
    // fine (falls back to each letter's own default spacing), but a value
    // that IS entered must be a whole number within [min, max].
    $('#signature_space_input').on('input', function () {
        var $input = $(this);
        var $error = $('#signature_space_error');
        var val    = $input.val();
        $error.hide().text('');

        if (val.trim() === '') {
            return;
        }

        var min = parseInt($input.attr('min'), 10);
        var max = parseInt($input.attr('max'), 10);
        var num = parseInt(val, 10);

        if (isNaN(num) || String(num) !== val.trim() || num < min || num > max) {
            $error.text('Must be a whole number between ' + min + ' and ' + max + '.').show();
        }
    });

    // --- AJAX submit ---------------------------------------------------------
    // A real FormData built from the form element itself already carries the
    // hidden CSRF field from csrf_field(), so no extra header is needed here
    // (unlike a JSON-body AJAX post, which has no form fields to carry it).
    $('#institute_form').on('submit', function (e) {
        e.preventDefault();

        $('#signature_space_input').trigger('input');

        if ($('#logo_error:visible, #letterhead_error:visible, #signature_space_error:visible').length > 0) {
            esNotify('error', 'Fix the errors above before saving');
            return;
        }

        var $form     = $(this);
        var $btn      = $('#institute_submit_btn').prop('disabled', true);
        var formData  = new FormData(this);

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (res) {
            $btn.prop('disabled', false);

            if (!res || res.status !== 'success') {
                esNotify('error', 'Could not save', (res && res.message) || 'Please try again.');
                return;
            }

            esNotify('success', 'Institute details updated');

            // Swap the previews to the server's saved paths (not the
            // client-side FileReader data-URL) and clear the file inputs, so
            // a resubmit without picking a new file correctly means "keep it".
            var data = res.data || {};
            if (data.institute_logo_path) {
                $('#logo_preview').attr('src', '<?= base_url() ?>' + data.institute_logo_path).show();
            }
            if (data.institute_letterhead_path) {
                $('#letterhead_preview').attr('src', '<?= base_url() ?>' + data.institute_letterhead_path).show();
            }
            $('#logo_input, #letterhead_input').val('');
        }).fail(function (xhr) {
            $btn.prop('disabled', false);
            esAjaxError(xhr, 'Institute details could not be saved');
        });
    });
});
</script>
