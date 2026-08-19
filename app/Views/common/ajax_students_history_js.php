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

    // --- the three batch-derived pickers --------------------------------
    //
    // api/batch-* rather than api/academic-years or api/streams: these list
    // what the verification records actually contain, so every option offered
    // matches at least one batch. The course case makes the difference
    // concrete -- stream_details holds "BCOM"/"MCOM" while every batch stores
    // "F.Y.B.Com"/"MCOM Part I", the two sets share nothing, and the filter is
    // an exact comparison. Fed from api/streams this picker would have offered
    // 30 courses and found no batch for any of them.

    esAjaxSelect('.select2-batch-year', '<?= base_url('api/batch-years') ?>', {
        placeholder: '-- All Years --',
        context: 'Loading admission years failed'
    });

    esAjaxSelect('.select2-batch-course', '<?= base_url('api/batch-courses') ?>', {
        placeholder: '-- All Courses --',
        context: 'Loading courses failed'
    });

    // tags: this filter is a LIKE server-side, and it replaces a free-text
    // input. Without the typed term as a selectable option, "show me every
    // Mumbai university" -- which the box did yesterday -- would stop being
    // expressible at all.
    esAjaxSelect('.select2-batch-university', '<?= base_url('api/batch-universities') ?>', {
        placeholder: '-- All Universities --',
        tags: true,
        context: 'Loading universities failed'
    });

    var $wrap    = $('#batch_history_wrap');
    var $rows    = $('#batch_history_rows');
    var $pager   = $('#batch_history_pager');
    var $loading = $('#batch_history_loading');
    var $form    = $('#batch_history_filters');

    // --- View: a dialog rather than a page ------------------------------
    //
    // Bound BEFORE the early return below. That guard exists for the paging
    // code, which needs the history table; this needs the dialog, and the two
    // are not on the same screens -- batch_detail.php includes this same file
    // and has neither.
    (function () {
        var $modalEl = $('#batchViewModal');

        if ($modalEl.length === 0 || !window.bootstrap) {
            return;   // not this screen, or Bootstrap absent: the link still works
        }

        var modal      = new window.bootstrap.Modal($modalEl[0]);
        var $viewRows  = $('#batch_view_rows');
        var $viewWrap  = $('#batch_view_wrap');
        var $viewBusy  = $('#batch_view_loading');
        var $viewRef   = $('#batch_view_ref');
        var $viewMeta  = $('#batch_view_meta');
        var viewReq    = null;

        $(document).on('click', 'a.js-view-batch', function (e) {
            // Let the browser have the ones that are not a plain click:
            // ctrl/cmd-click, shift-click and middle-click all mean "open this
            // somewhere else", and swallowing them would make the button worse
            // than the link it replaced.
            if (e.which > 1 || e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }

            e.preventDefault();

            var $link = $(this);
            var $row  = $link.closest('tr');

            $viewRef.text($link.data('batch') || '');

            // Context from the row already on screen -- no second request for
            // something the operator is looking at.
            var university = $.trim($row.children('td').eq(1).text());
            $viewMeta.text(university);

            $viewRows.empty();
            $viewWrap.addClass('d-none');
            $viewBusy.removeClass('d-none');

            modal.show();

            // Reopening while a slower request is still running would otherwise
            // paint the previous batch into the dialog.
            if (viewReq && viewReq.readyState !== 4) {
                viewReq.abort();
            }

            viewReq = $.ajax({
                url: $link.attr('href'),
                type: 'GET',
                dataType: 'json'
            }).done(function (res) {
                if (!res || res.status !== 'success') {
                    esNotify('error', 'Could not load that batch');
                    modal.hide();
                    return;
                }

                $viewRows.html(res.rows);
                $viewWrap.removeClass('d-none');

                var n = Number(res.count) || 0;
                $viewMeta.text(
                    (university ? university + '  \u00b7  ' : '') +
                    n.toLocaleString() + ' candidate' + (n === 1 ? '' : 's')
                );
            }).fail(function (xhr, status) {
                if (status === 'abort') {
                    return;   // superseded by a newer open
                }

                modal.hide();
                esAjaxError(xhr, 'Could not load that batch');
            }).always(function () {
                $viewBusy.addClass('d-none');
            });
        });

        // Drop the rows on close: leaving one batch's candidates in the DOM
        // means the next open flashes them before its own request lands.
        $modalEl.on('hidden.bs.modal', function () {
            if (viewReq && viewReq.readyState !== 4) {
                viewReq.abort();
            }

            $viewRows.empty();
            $viewWrap.addClass('d-none');
            $viewMeta.text('');
        });
    }());

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

            // No separate count to update: the pager bar carries the range and
            // the total, and it arrives inside res.pager already rendered by
            // the same template the full page used. res.total stays in the
            // reply -- it is part of the published response shape.

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
