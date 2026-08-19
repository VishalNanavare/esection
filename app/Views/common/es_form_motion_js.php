<script {csp-script-nonce}>
/**
 * Submit-button motion.
 *
 * Deliberately dependency-free -- jQuery only. It is included by BOTH layouts,
 * and the auth layout deliberately carries no Select2, SweetAlert or
 * ajax_common_js, so anything reaching for those would break sign-in.
 *
 * What it does: while a form is submitting, its submit button shows a spinner
 * and stops accepting further clicks. Login had no feedback of any kind -- the
 * button simply sat there while the request ran, which reads as a dead click.
 */
(function (window, $) {
    'use strict';

    if (!$) {
        return;
    }

    var BUSY = 'es-btn-busy';

    function startBusy($btn) {
        if ($btn.length === 0 || $btn.hasClass(BUSY)) {
            return;
        }

        // Width is pinned before the label changes, so the button cannot
        // visibly resize as the spinner replaces the icon.
        $btn.css('min-width', $btn.outerWidth() + 'px');

        $btn.data('es-label', $btn.html())
            .addClass(BUSY)
            .prop('disabled', true)
            .attr('aria-busy', 'true');
    }

    function endBusy($btn) {
        if ($btn.length === 0 || !$btn.hasClass(BUSY)) {
            return;
        }

        var label = $btn.data('es-label');

        if (typeof label === 'string') {
            $btn.html(label);
        }

        $btn.removeClass(BUSY)
            .prop('disabled', false)
            .removeAttr('aria-busy')
            .css('min-width', '');
    }

    $(document).on('submit', 'form', function (e) {
        // A submit another handler has cancelled -- client-side validation, a
        // confirmation dialog -- is not a submit. Marking the button busy here
        // would leave it spinning forever on a form that never went anywhere.
        if (e.isDefaultPrevented()) {
            return;
        }

        var $form = $(this);

        if ($form.is('[data-no-busy]')) {
            return;
        }

        startBusy($form.find('button[type="submit"], input[type="submit"]').first());
    });

    // Restored from the back/forward cache, the page comes back exactly as it
    // was left -- including a button still spinning from the navigation that
    // took the user away. Always clear it.
    $(window).on('pageshow', function () {
        endBusy($('.' + BUSY));
    });

    window.esButtonBusy = startBusy;
    window.esButtonIdle = endBusy;
})(window, jQuery);
</script>
