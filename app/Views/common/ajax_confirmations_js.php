<script>
$(document).ready(function() {
    // Initialize AJAX Select2 for Stream Filter
    $('.select2-ajax-stream').select2({
        width: '100%',
        ajax: {
            url: '<?= base_url("api/streams") ?>',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return { results: data.results };
            }
        }
    });

    $('#check_all_conf').on('change', function() {
        $('.conf-check').prop('checked', $(this).prop('checked'));
    });
});
</script>
