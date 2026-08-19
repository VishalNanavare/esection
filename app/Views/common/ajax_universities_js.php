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
    <?php /*
      The client-side filter that used to live here has gone.

      It narrowed only the rows already rendered, which meant Export produced
      the whole directory regardless of what was on screen, and a filtered view
      could be neither bookmarked nor sent to anybody. Filtering is now a plain
      GET form handled by Universities::index(), so the query string is the
      single source of truth for the table AND the export.

      Removed with it: applyFilter(), the #filter_count counter, the
      #no_filter_match note and the es:refreshed re-run they needed -- all of
      which existed only to keep DOM-derived state honest after an AJAX row
      swap. Server-rendered rows do not have that problem.
    */ ?>

    <?php /*
      Filtering moved server-side, so the rows returned by an AJAX save are
      the UNFILTERED set -- the controller re-renders them without a query
      string. Swapping those in under an active filter would silently widen
      the table back to every university straight after a save.

      Reloading keeps the URL, and with it the filter, so the operator sees
      their saved change inside the view they were working in. Only when a
      filter is actually active: without one the in-place swap is still the
      better, flicker-free path.
    */ ?>
    $(document).on('es:refreshed', function () {
        var query = window.location.search;

        if (query.indexOf('name=') !== -1 || query.indexOf('state=') !== -1) {
            window.location.reload();
        }
    });

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
