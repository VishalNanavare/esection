<script {csp-script-nonce}>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    esAjaxSelect('.select2-ajax-academic-year', '<?= base_url("api/academic-years") ?>', {
        context: 'Loading academic years failed'
    });

    esBindCheckAll('#check_all_conf', '.conf-check');

    // --- Conditional "Confirmation From" panel ------------------------------
    // Mirrors con_view.php's own show/hide: the confirmation-from details only
    // matter once Migration/TC is marked "Yes" for that student's row.
    $(document).on('change', '.mig-tc-select', function () {
        // The clarification fields now live in their own full-width row
        // beneath the candidate, rather than crammed into the REMARK cell, so
        // this reaches the NEXT row instead of into the current one.
        var $row    = $(this).closest('tr');
        var $detail = $row.next('.conf-detail-row');

        if ($(this).val() === 'Yes') {
            $detail.removeAttr('hidden').addClass('conf-detail-row--open');
            return;
        }

        $detail.attr('hidden', 'hidden').removeClass('conf-detail-row--open');
        $detail.find('select').val('');
        $detail.find('.conf-from-other').val('').hide();
    });

    $(document).on('change', '.conf-from-select', function () {
        // Scoped to the detail row now, not the removed .conf-from-panel.
        var $other = $(this).closest('.conf-detail-row').find('.conf-from-other');

        $other.toggle($(this).val() === 'other');

        if ($(this).val() !== 'other') {
            $other.val('');
        }
    });
});
</script>
