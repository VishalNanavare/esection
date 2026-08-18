<script {csp-script-nonce}>
$(document).ready(function () {
    esAjaxSelect('.select2-ajax-state', '<?= base_url("api/states") ?>', {
        context: 'Loading states failed'
    });

    esAjaxSelect('.select2-ajax-college', '<?= base_url("api/colleges") ?>', {
        context: 'Loading universities failed',
        // This IS the university management page -- unlike every other page
        // using this same endpoint to pick a target for a new letter, this
        // filter must also find deactivated rows so they can be reactivated.
        extraParams: { active_only: '0' }
    });

    // --- Client-side row filter -------------------------------------------
    // Previously this read `option:selected).text()`, which returns the
    // literal placeholder "-- All Universities --" when nothing is selected.
    // That string is truthy, so nameMatch compared every row against it and
    // picking any state hid the entire table. Now it compares ids, which is
    // exactly what /api/colleges returns as results[].id, so the brittle
    // "strip the (State) suffix" regex is gone too -- that regex also
    // mangled any university with parentheses in its own name.
    // Looked up live on every call, NOT cached at ready time. #university_rows
    // is replaced wholesale by every AJAX save, so a snapshot taken here would
    // point at detached nodes from then on: the filter would toggle rows that
    // are no longer in the document and the counter would report against the
    // old row count ("Showing 0 of 47").
    var $countNote = $('#filter_count');

    function applyFilter() {
        var $rows = $('.uni-row');
        var state = $('#state_filter').val() || '';
        var uniId = $('#university_name_filter').val() || '';
        var shown = 0;

        $rows.each(function () {
            var $row       = $(this);
            var stateMatch = !state || String($row.data('state')) === String(state);
            var nameMatch  = !uniId || String($row.data('id')) === String(uniId);
            var visible    = stateMatch && nameMatch;

            $row.toggle(visible);
            if (visible) {
                shown++;
            }
        });

        if ($countNote.length) {
            $countNote.text(
                (shown === $rows.length)
                    ? ($rows.length + ' universities')
                    : ('Showing ' + shown + ' of ' + $rows.length + ' universities')
            );
        }

        $('#no_filter_match').toggle(shown === 0 && $rows.length > 0);
    }

    $('#state_filter, #university_name_filter').on('change', applyFilter);

    $('#reset_filters').on('click', function () {
        $('#state_filter').val(null).trigger('change.select2');
        $('#university_name_filter').val(null).trigger('change.select2');
        applyFilter();
    });

    applyFilter();

    // The counter and the "no matches" note are computed from the DOM, not
    // rendered server-side, so they stay stale after a row swap unless the
    // filter is re-run against the new rows.
    $(document).on('es:refreshed', applyFilter);

    // --- Edit modal --------------------------------------------------------
    // Delegated: these buttons live inside #university_rows.
    $(document).on('click', '.edit-uni-btn', function () {
        var id = $(this).data('id');

        $.ajax({
            url: '<?= base_url("universities/getJson/") ?>' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json'
        }).done(function (res) {
            if (!res || res.status !== 'success' || !res.data) {
                esNotify('error', 'Could not load university', (res && res.message) || 'Unexpected response.');
                return;
            }

            var data = res.data;
            $('#edit_name').val(data.Name);
            $('#edit_state').val(data.States);
            $('#edit_head_name').val(data.head_name);
            $('#edit_fees').val(data.fees);
            $('#edit_in_favour_of').val(data.in_favour_of);
            $('#edit_address').val(data.Address);
            $('#edit_email_id').val(data.email_id);
            $('#edit_mobile_no').val(data.mobile_no);
            $('#edit_university_form').attr('action', '<?= base_url("universities/update/") ?>' + id);
            $('#editUniversityModal').modal('show');
        }).fail(function (xhr) {
            esAjaxError(xhr, 'Could not load the university record');
        });
    });
});
</script>
