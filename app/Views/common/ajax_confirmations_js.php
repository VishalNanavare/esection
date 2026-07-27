<script>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    esBindCheckAll('#check_all_conf', '.conf-check');
});
</script>
