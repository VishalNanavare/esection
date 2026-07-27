<script>
$(document).ready(function() {
    // Initialize AJAX Select2 for State Filter
    $('.select2-ajax-state').select2({
        width: '100%',
        ajax: {
            url: '<?= base_url("api/states") ?>',
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

    // Initialize AJAX Select2 for College Filter
    $('.select2-ajax-college').select2({
        width: '100%',
        ajax: {
            url: '<?= base_url("api/colleges") ?>',
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

    // Live Filter Table
    $('#state_filter, #university_name_filter').on('change', function() {
        var selectedState = $('#state_filter').val();
        var selectedNameText = $('#university_name_filter option:selected').text();
        var selectedName = selectedNameText.replace(/\s*\([^)]*\)/, '').trim();

        $('.uni-row').each(function() {
            var stateMatch = !selectedState || $(this).data('state') === selectedState;
            var nameMatch = !selectedNameText || $(this).data('name') === selectedName;

            if (stateMatch && nameMatch) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('#reset_filters').on('click', function() {
        $('#state_filter').val('').trigger('change');
        $('#university_name_filter').val('').trigger('change');
        $('.uni-row').show();
    });

    // Edit University Modal AJAX Load
    $('.edit-uni-btn').on('click', function() {
        var id = $(this).data('id');
        $.ajax({
            url: '<?= base_url("universities/getJson/") ?>' + id,
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    var data = res.data;
                    $('#edit_name').val(data.Name);
                    $('#edit_state').val(data.States);
                    $('#edit_head_name').val(data.head_name);
                    $('#edit_fees').val(data.fees);
                    $('#edit_in_favour_of').val(data.in_favour_of);
                    $('#edit_address').val(data.Address);

                    $('#edit_university_form').attr('action', '<?= base_url("universities/update/") ?>' + id);
                    $('#editUniversityModal').modal('show');
                }
            }
        });
    });

    // SweetAlert2 Delete Confirmation
    $('.delete-uni-btn').on('click', function(e) {
        e.preventDefault();
        var deleteUrl = $(this).attr('href');
        Swal.fire({
            title: 'Delete University Record?',
            text: 'This university record will be permanently deleted from the directory.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            cancelButtonText: 'Cancel'
        }).then(function(result) {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
    });
});
</script>
