<script {csp-script-nonce}>
$(document).ready(function () {
    // Deep-link highlight from the "Already confirmed" link on
    // confirmations/index.php -- scroll the specific student's row into
    // view among their batch-mates on confirmations/batch/{id}.
    var $highlighted = $('.confirmation-row-highlight');
    if ($highlighted.length) {
        $highlighted[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

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
        if (res && res.remaining === 0) {
            esNotify('info', 'Batch empty', 'That was the last record -- returning to history.');
            window.setTimeout(function () {
                window.location.href = '<?= base_url("confirmations/history") ?>';
            }, 1200);
        }
    });
});
</script>
