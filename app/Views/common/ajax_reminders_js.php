<script {csp-script-nonce}>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    esAjaxSelect('.select2-ajax-academic-year', '<?= base_url("api/academic-years") ?>', {
        context: 'Loading academic years failed'
    });

    // Reminder letters are addressed to a university by name, so the option
    // value is the name rather than the numeric id.
    esAjaxSelect('.select2-ajax-college', '<?= base_url("api/colleges") ?>', {
        context: 'Loading universities failed',
        mapItem: function (item) {
            return { id: item.name, text: item.text };
        }
    });

    esBindCheckAll('#check_all', '.stud-check');
});
</script>
