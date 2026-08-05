<script>
/**
 * Shared AJAX / Select2 plumbing.
 *
 * Included from layouts/app.php AFTER jQuery, Select2 and SweetAlert2, and
 * BEFORE renderSection('scripts'), so every view partial can rely on it.
 *
 * Exists because the five ajax_*_js partials each hand-rolled their own
 * Select2 config. Three of them dropped `pagination` from processResults,
 * which silently capped every college dropdown at the first 20 of 470 rows,
 * and none of the eleven AJAX calls defined an `error` handler at all.
 */
(function (window, $) {
    'use strict';

    var LOGIN_URL = <?= json_encode(base_url('auth/login'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    /**
     * Escape a value for safe interpolation into an HTML string.
     * Required wherever we build markup by concatenation.
     */
    function esEscapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).replace(/[&<>"']/g, function (c) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[c];
        });
    }

    /** Non-blocking corner toast. Falls back to console if Swal is absent. */
    function esNotify(icon, title, text) {
        if (!window.Swal) {
            (window.console || {}).log && console.log('[' + icon + '] ' + title + ' ' + (text || ''));
            return;
        }

        window.Swal.fire({
            toast: true,
            position: 'top-end',
            icon: icon,
            title: title,
            text: text || undefined,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }

    /**
     * Single place that decides what an AJAX failure means.
     *
     * AuthFilter answers API/XHR requests with 401 JSON rather than a 302 to
     * an HTML login page, so an expired session is now detectable instead of
     * surfacing as a jQuery `parsererror`.
     */
    function esAjaxError(xhr, context) {
        if (!xhr || xhr.status === 0) {
            return; // request aborted (navigation, or Select2 superseding a query)
        }

        if (xhr.status === 401) {
            var target = LOGIN_URL;
            try {
                if (xhr.responseJSON && xhr.responseJSON.login_url) {
                    target = xhr.responseJSON.login_url;
                }
            } catch (e) { /* ignore */ }

            window.location.href = target;
            return;
        }

        if (xhr.status === 403) {
            esNotify('warning', 'Security token expired', 'Please reload the page and try again.');
            return;
        }

        esNotify('error', 'Could not load data', (context || 'The server request failed') + ' (HTTP ' + xhr.status + ').');
    }

    /**
     * Wire an AJAX-backed Select2.
     *
     * @param {string}   selector  jQuery selector for the <select>
     * @param {string}   url       endpoint returning {results, pagination}
     * @param {object=}  options   { mapItem, context, dropdownParent, minimumInputLength, extraParams }
     */
    function esAjaxSelect(selector, url, options) {
        var $el = $(selector);
        if ($el.length === 0) {
            return $el;
        }

        options = options || {};

        var config = {
            width: '100%',
            allowClear: false,
            ajax: {
                url: url,
                dataType: 'json',
                delay: 250,
                cache: true,
                data: function (params) {
                    // `page` is mandatory: without it Select2 never requests
                    // page 2 and the list is capped at the first page.
                    var data = { q: params.term || '', page: params.page || 1 };
                    // Static extra query params (e.g. active_only) a specific
                    // page's picker needs to send on every request -- unused
                    // by default, so every existing call site is unaffected.
                    return options.extraParams ? $.extend({}, data, options.extraParams) : data;
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;

                    var results = (data && data.results) || [];
                    if (typeof options.mapItem === 'function') {
                        results = results.map(options.mapItem);
                    }

                    return {
                        results: results,
                        // Preserve the server's `more` flag so infinite scroll
                        // actually terminates instead of never starting.
                        pagination: (data && data.pagination) || { more: false }
                    };
                },
                error: function (xhr) {
                    esAjaxError(xhr, options.context || 'Loading options failed');
                }
            }
        };

        if (options.dropdownParent) {
            config.dropdownParent = options.dropdownParent;
        }
        if (options.minimumInputLength) {
            config.minimumInputLength = options.minimumInputLength;
        }

        return $el.select2(config);
    }

    /**
     * Keep a "select all" checkbox in sync with its rows, including the
     * indeterminate state when only some rows are ticked.
     */
    function esBindCheckAll(masterSelector, rowSelector) {
        var $master = $(masterSelector);
        if ($master.length === 0) {
            return;
        }

        function sync() {
            var $rows   = $(rowSelector);
            var total   = $rows.length;
            var checked = $rows.filter(':checked').length;

            $master.prop('checked', total > 0 && checked === total);
            $master.prop('indeterminate', checked > 0 && checked < total);
        }

        $master.on('change', function () {
            $(rowSelector).prop('checked', $(this).prop('checked'));
            sync();
        });

        $(document).on('change', rowSelector, sync);
        sync();
    }

    /**
     * Flatpickr on every `.es-datepicker` input (a plain text input, not a
     * native `type="date"` -- flatpickr fully replaces the calendar UI, and
     * a native date input's own browser-drawn picker would otherwise still
     * pop up underneath/alongside it). `dateFormat: 'Y-m-d'` matches what a
     * native date input already submitted, so no backend changes were
     * needed anywhere this replaces one.
     *
     * Deliberately NOT using flatpickr's `altInput` option: several edit
     * modals populate their date field with plain `$('#field').val(x)`
     * (fetched via AJAX, set directly, no flatpickr API involved) --
     * altInput keeps the real value in a second hidden input and only
     * flatpickr's own setDate() updates its separate visible text field, so
     * a raw `.val()` write would silently desync what's displayed from
     * what's actually submitted. Binding flatpickr directly to the one
     * real input (no alt) means `.val()` keeps working exactly as every
     * existing populate call site already expects.
     *
     * Called automatically below for every page (auto-init, not opt-in per
     * page), so a future date field just needs the `es-datepicker` class,
     * no JS partial changes required.
     */
    function esInitDatePickers() {
        if (!window.flatpickr) {
            return;
        }
        $('.es-datepicker').each(function () {
            if (this._flatpickr) {
                return;
            }
            window.flatpickr(this, {
                dateFormat: 'Y-m-d',
                allowInput: true
            });
        });
    }

    window.esEscapeHtml  = esEscapeHtml;
    window.esNotify      = esNotify;
    window.esAjaxError   = esAjaxError;
    window.esAjaxSelect  = esAjaxSelect;
    window.esBindCheckAll = esBindCheckAll;
    window.esInitDatePickers = esInitDatePickers;

    $(function () { esInitDatePickers(); });
})(window, jQuery);
</script>
