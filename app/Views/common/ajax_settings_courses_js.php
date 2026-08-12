<script>
$(document).ready(function () {
    // --- Edit modal ---------------------------------------------------------
    // Delegated: these buttons live inside #course_rows, which is replaced
    // wholesale on every AJAX refresh.
    $(document).on('click', '.edit-course-btn', function () {
        var id = $(this).data('id');

        $.ajax({
            url: '<?= base_url("settings/courses/getJson/") ?>' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data) {
                esNotify('error', 'Could not load course', (res && res.message) || 'Unexpected response.');
                return;
            }

            var data = res.data;
            $('#edit_course_name').val(data.name);
            $('#edit_course_code').val(data.code);
            $('#edit_course_form').attr('action', '<?= base_url("settings/courses/update/") ?>' + id);
            $('#editCourseModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the course record');
        });
    });
});
</script>
