<script {csp-script-nonce}>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-stream', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    // This form persists the university NAME, not its id, so the option
    // value is remapped. `text` still carries the "Name (State)" label.
    esAjaxSelect('.select2-ajax-college', '<?= base_url("api/colleges") ?>', {
        context: 'Loading universities failed',
        mapItem: function (item) {
            return { id: item.name, text: item.text };
        }
    });

    esAjaxSelect('.select2-ajax-admission-course', '<?= base_url("api/streams") ?>', {
        context: 'Loading academic streams failed'
    });

    esAjaxSelect('.select2-ajax-academic-year', '<?= base_url("api/academic-years") ?>', {
        context: 'Loading academic years failed'
    });
});
</script>
