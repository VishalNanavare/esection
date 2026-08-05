<script>
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
        var $panel = $(this).closest('tr').find('.conf-from-panel');

        if ($(this).val() === 'Yes') {
            $panel.show();
        } else {
            $panel.hide();
            $panel.find('select').val('');
            $panel.find('.conf-from-other').val('').hide();
        }
    });

    $(document).on('change', '.conf-from-select', function () {
        var $other = $(this).closest('.conf-from-panel').find('.conf-from-other');
        $other.toggle($(this).val() === 'other');
        if ($(this).val() !== 'other') {
            $other.val('');
        }
    });
});
</script>
