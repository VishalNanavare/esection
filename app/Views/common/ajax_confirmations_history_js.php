<script {csp-script-nonce}>
$(document).ready(function () {
    // Deep-link highlight from the "Already confirmed" link on
    // confirmations/index.php -- scroll the specific student's row into
    // view among their batch-mates on confirmations/batch/{id}.
    var $highlighted = $('.confirmation-row-highlight');
    if ($highlighted.length) {
        $highlighted[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // --- View: a dialog rather than a page ------------------------------
    //
    // Scoped by the dialog's own presence, the same way the Select2 calls below
    // are scoped by theirs: this file is included by BOTH confirmations/history
    // and confirmations/batch_detail, and only the first has the modal.
    (function () {
        var $modalEl = $('#confBatchViewModal');

        if ($modalEl.length === 0 || !window.bootstrap) {
            return;   // not this screen, or Bootstrap absent: the link still works
        }

        var modal     = new window.bootstrap.Modal($modalEl[0]);
        var $viewRows = $('#conf_view_rows');
        var $viewWrap = $('#conf_view_wrap');
        var $viewBusy = $('#conf_view_loading');
        var $viewRef  = $('#conf_view_ref');
        var $viewMeta = $('#conf_view_meta');
        var $viewPdf  = $('#conf_view_pdf');
        var viewReq   = null;

        $(document).on('click', 'a.js-view-conf-batch', function (e) {
            // Anything that is not a plain click means "open this somewhere
            // else" -- ctrl/cmd, shift, alt, middle button. Swallowing those
            // would make the button worse than the link it replaced.
            if (e.which > 1 || e.shiftKey || e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }

            e.preventDefault();

            var $link = $(this);
            var $row  = $link.closest('tr');
            var ref   = $link.data('batch');

            $viewRef.text(ref === undefined ? '' : ref);

            // Context off the row already on screen rather than a second
            // request for something the operator is looking at.
            var confirmedOn = $.trim($row.children('td').eq(1).text());
            $viewMeta.text(confirmedOn ? 'Confirmed ' + confirmedOn : '');

            // The letter is per batch, so it can be pointed before the records
            // arrive -- it does not depend on the reply.
            if ($viewPdf.length) {
                $viewPdf.attr('href', '<?= base_url('confirmations/eligibilityPdf/') ?>' + encodeURIComponent(ref));
            }

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
                    (confirmedOn ? 'Confirmed ' + confirmedOn + '  \u00b7  ' : '') +
                    n.toLocaleString() + ' record' + (n === 1 ? '' : 's')
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

        // Drop the rows on close: leaving one batch's records in the DOM means
        // the next open flashes them before its own request lands.
        $modalEl.on('hidden.bs.modal', function () {
            if (viewReq && viewReq.readyState !== 4) {
                viewReq.abort();
            }

            $viewRows.empty();
            $viewWrap.addClass('d-none');
            $viewMeta.text('');
        });
    }());

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

    // --- Delete confirm -------------------------------------------------------
    // Declared on the form itself (confirmations/_batch_rows.php) and handled by
    // the shared delegated handler in ajax_common_js.php.
    //
    // The binding that used to live here ended in `form.submit()`, which
    // dispatches NO submit event, so a delegated AJAX handler could never have
    // observed it -- and running both would have deleted the record while the
    // confirmation dialog was still waiting for an answer.

    // --- Leaving an emptied batch --------------------------------------------
    // batchDetail() has no emptiness guard, so once the last record goes the
    // page describes a batch that no longer exists. A full reload used to hide
    // that; without one, go back to the history list.
    $(document).on('es:refreshed', function (e, $form, res) {
        // Only on the batch detail page. This file is included by
        // confirmations/history too, where #confirmation_batch_rows does not
        // exist -- and "the batch is empty, returning to history" fired there
        // would be a surprise reload of the list the operator is already
        // looking at. Checked per event rather than once at ready, so it stays
        // right if the region is ever swapped in.
        if ($('#confirmation_batch_rows').length === 0) {
            return;
        }

        if (res && res.remaining === 0) {
            esNotify('info', 'Batch empty', 'That was the last record -- returning to history.');
            window.setTimeout(function () {
                window.location.href = '<?= base_url("confirmations/history") ?>';
            }, 1200);
        }
    });
});
</script>
