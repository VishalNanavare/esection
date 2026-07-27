<script>
$(document).ready(function() {
    $('.select2-ajax-stream').select2({
        width: '100%',
        ajax: {
            url: '<?= base_url("api/streams") ?>',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.results }; }
        }
    });

    $('.select2-ajax-college').select2({
        width: '100%',
        ajax: {
            url: '<?= base_url("api/colleges") ?>',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) {
                var items = data.results.map(function(item) {
                    return { id: item.name, text: item.text };
                });
                return { results: items };
            }
        }
    });

    $('#check_all').on('change', function() {
        $('.stud-check').prop('checked', $(this).prop('checked'));
    });
});
</script>
