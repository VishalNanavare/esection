<script {csp-script-nonce}>
/**
 * Show / hide for every password field.
 *
 * Auto-enhancing on purpose: it finds input[type="password"] itself rather
 * than each view opting in. There are eleven password fields across seven
 * files -- login, the reset screen, the change-password dialog in the layout,
 * user create and edit, the backup password pair and SMTP -- and hand-marking
 * them would guarantee the next one added is missed.
 *
 * Dependency-free (jQuery only), because the auth layout deliberately carries
 * no Select2, SweetAlert or ajax_common_js and the login field is the one that
 * prompted this.
 *
 * Two layouts are handled differently, because one markup shape cannot serve
 * both:
 *
 *   inside .input-group   the button is appended as a sibling, so Bootstrap's
 *                         own flex row lays it out and it reads as part of the
 *                         control. Wrapping the input instead would make the
 *                         wrapper the flex child and break the joins.
 *
 *   standalone            the input is wrapped in a relative span and the
 *                         button sits over its right padding -- the same shape
 *                         .es-search uses for the batch filter.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    var EYE      = 'fa-eye';
    var EYE_SLASH = 'fa-eye-slash';

    function buttonHtml(extraClass) {
        return '<button type="button" class="es-pw-toggle ' + extraClass + '" '
             + 'tabindex="-1" aria-pressed="false" aria-label="Show password" '
             + 'title="Show password"><i class="fa ' + EYE + '" aria-hidden="true"></i></button>';
    }

    function enhance(input) {
        var $input = $(input);

        // Already done, or explicitly opted out.
        if ($input.data('es-pw') || $input.is('[data-no-pw-toggle]')) {
            return;
        }

        $input.data('es-pw', true);

        var $group = $input.parent('.input-group');
        var $btn;

        if ($group.length) {
            // Bootstrap lays this out; it must be a direct child of the group
            // and must come after the input to sit on the right.
            $btn = $(buttonHtml('es-pw-toggle--group'));
            $input.after($btn);
        } else {
            $input.wrap('<span class="es-pw"></span>');
            $btn = $(buttonHtml('es-pw-toggle--inline'));
            $input.after($btn);
        }

        $btn.on('click', function () {
            var showing = $input.attr('type') === 'text';

            // Caret position is preserved across the type swap. Without this
            // the cursor jumps to the end, which is maddening when someone is
            // checking a character mid-string.
            var start = null;
            var end   = null;

            try {
                start = $input[0].selectionStart;
                end   = $input[0].selectionEnd;
            } catch (e) {
                start = null;
            }

            $input.attr('type', showing ? 'password' : 'text');

            // Marks a currently-revealed field, so the pagehide sweep below
            // can find it. Without the marker that sweep matched nothing and
            // silently did nothing at all.
            if (showing) {
                $input.removeAttr('data-es-reveal');
            } else {
                $input.attr('data-es-reveal', '1');
            }

            $(this)
                .attr('aria-pressed', showing ? 'false' : 'true')
                .attr('aria-label', showing ? 'Show password' : 'Hide password')
                .attr('title', showing ? 'Show password' : 'Hide password')
                .find('i')
                .toggleClass(EYE, showing)
                .toggleClass(EYE_SLASH, !showing);

            // Focus returns to the field, so revealing a password does not
            // cost the typist their place in the form.
            $input.trigger('focus');

            if (start !== null) {
                try {
                    $input[0].setSelectionRange(start, end);
                } catch (e) {
                    // Some input types refuse setSelectionRange; harmless.
                }
            }
        });
    }

    function enhanceAll(root) {
        $(root || document).find('input[type="password"]').each(function () {
            enhance(this);
        });
    }

    $(function () {
        enhanceAll(document);

        // Fields inside a dialog exist in the DOM already, but a screen that
        // builds a form after load would otherwise be missed.
        $(document).on('shown.bs.modal', function (e) {
            enhanceAll(e.target);
        });
    });

    // Anything revealed must go back to hidden before the page is left, so a
    // password is never left on screen behind a browser's back/forward cache
    // or in a screenshot of the next page's load.
    $(window).on('pagehide', function () {
        $('input[type="text"][data-es-reveal]').attr('type', 'password');
    });

    window.esEnhancePasswordFields = enhanceAll;
})(window, jQuery);
</script>
