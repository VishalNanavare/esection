<script {csp-script-nonce}>
/**
 * Batch History: paging and filtering without a full page load.
 *
 * The screen used to render every batch in one response -- 3,934 of them, and
 * roughly six megabytes of table markup. It is paginated server-side now, and
 * this fetches a page at a time.
 *
 * The server returns the SAME partials the full page is built from, so a paged
 * or filtered view cannot drift from a freshly loaded one. Nothing here builds
 * table markup in JavaScript.
 */
$(document).ready(function () {
    'use strict';

    var $wrap    = $('#batch_history_wrap');
    var $rows    = $('#batch_history_rows');
    var $pager   = $('#batch_history_pager');
    var $count   = $('#batch_history_count');
    var $loading = $('#batch_history_loading');
    var $form    = $('#batch_history_filters');

    if ($rows.length === 0) {
        return;
    }

    var base    = '<?= base_url('students/history') ?>';
    var request = null;

    function busy(on) {
        $loading.toggleClass('d-none', !on);
        $wrap.css('opacity', on ? 0.55 : 1);
    }

    /**
     * Loads a page.
     *
     * @param {string} query already-encoded query string, without the '?'
     * @param {bool}   push  whether to add a history entry
     */
    function load(query, push) {
        // A fast click through the pager can leave two requests in flight, and
        // the slower one would win and paint the wrong page. Abort the previous
        // one rather than racing it.
        if (request && request.readyState !== 4) {
            request.abort();
        }

        busy(true);

        request = $.ajax({
            url: base + (query ? '?' + query : ''),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success') {
                esNotify('error', 'Could not load that page');
                return;
            }

            $rows.html(res.rows);
            $pager.html(res.pager || '');

            $count.text(
                res.total > 0
                    ? Number(res.total).toLocaleString() + ' batch' + (res.total === 1 ? '' : 'es')
                    : ''
            );

            if (push) {
                // The URL stays honest, so the view can be reloaded, bookmarked
                // or sent to somebody and still be the same view.
                window.history.pushState({ esHistory: query }, '', base + (query ? '?' + query : ''));
            }

            // Scroll the table back into view -- paging while scrolled down
            // otherwise leaves the operator looking at the row after the ones
            // that just changed.
            if ($wrap.length && $wrap[0].getBoundingClientRect().top < 0) {
                $wrap[0].scrollIntoView({ behavior: esReducedMotion() ? 'auto' : 'smooth', block: 'start' });
            }
        }).fail(function (xhr, status) {
            if (status === 'abort') {
                return;   // superseded by a newer request; not a failure
            }

            esAjaxError(xhr, 'Could not load that page');
        }).always(function () {
            busy(false);
        });
    }

    // Delegated: the pager is replaced on every load, so a handler bound
    // directly to its links would be lost after the first page change.
    $(document).on('click', '#batch_history_pager a.page-link', function (e) {
        var href = $(this).attr('href') || '';

        if (href === '' || href === '#' || $(this).closest('.page-item').hasClass('disabled')) {
            e.preventDefault();
            return;
        }

        e.preventDefault();
        load(href.split('?')[1] || '', true);
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        // Dropping page= is deliberate: filtering to a smaller result set while
        // sitting on page 9 would otherwise land on a page that no longer
        // exists and show an empty table.
        load($form.serialize(), true);
    });

    // Back and forward have to move through the pages the operator actually
    // saw, not out of the screen entirely.
    $(window).on('popstate', function (event) {
        var state = event.originalEvent ? event.originalEvent.state : null;

        if (state && typeof state.esHistory === 'string') {
            load(state.esHistory, false);
        }
    });
});
</script>
