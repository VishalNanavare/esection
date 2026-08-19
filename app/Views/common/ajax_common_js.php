<script {csp-script-nonce}>
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
            // 403 has two distinct causes here and they need different advice.
            // AdminFilter and AccessFilter both answer 403 with a JSON body
            // carrying a specific message ("You do not have permission to
            // access this page."). A CSRF rejection has no body at all.
            // Telling someone whose account simply lacks a page grant to
            // "reload the page and try again" sends them round a loop that
            // cannot succeed, so only the bodyless case gets that advice.
            var deniedMessage = (xhr.responseJSON && xhr.responseJSON.message) || '';

            if (deniedMessage) {
                esNotify('warning', 'Not permitted', deniedMessage);
            } else {
                esNotify('warning', 'Security token expired', 'Please reload the page and try again.');
            }
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
     * Refresh a "select all" checkbox from its rows, including the
     * indeterminate state when only some are ticked.
     *
     * Split out of esBindCheckAll so it can be re-run after a table body is
     * replaced by AJAX. Re-running esBindCheckAll itself would double-bind:
     * it attaches to the master AND to document with no guard.
     */
    function esSyncCheckAll(masterSelector, rowSelector) {
        var $master = $(masterSelector);
        if ($master.length === 0) {
            return;
        }

        var $rows   = $(rowSelector);
        var total   = $rows.length;
        var checked = $rows.filter(':checked').length;

        $master.prop('checked', total > 0 && checked === total);
        $master.prop('indeterminate', checked > 0 && checked < total);
    }

    /**
     * Keep a "select all" checkbox in sync with its rows.
     *
     * Safe to call twice: the binding is stamped on the master element, because
     * the document-level row listener below would otherwise accumulate one
     * duplicate per call.
     */
    function esBindCheckAll(masterSelector, rowSelector) {
        var $master = $(masterSelector);
        if ($master.length === 0 || $master.data('esCheckAllBound')) {
            return;
        }
        $master.data('esCheckAllBound', true);

        function sync() {
            esSyncCheckAll(masterSelector, rowSelector);
        }

        $master.on('change', function () {
            $(rowSelector).prop('checked', $(this).prop('checked'));
            sync();
        });

        // Delegated, so rows that arrive from an AJAX refresh are covered too.
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

    // ------------------------------------------------------------------
    // POST plumbing
    //
    // Every write in the app goes through esSubmitForm. The forms keep their
    // real action/method, and every controller keeps a redirect-with-flash
    // branch, so if this file fails to load the whole app still works -- it
    // just reloads on each save, exactly as it used to.
    // ------------------------------------------------------------------

    var CSRF_HASH = <?= json_encode(csrf_hash(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    // nginx closes a FastCGI read at 600s. Sitting just past that means a
    // stalled request surfaces as our own "outcome unknown" message rather
    // than hanging on a spinner forever.
    var REQUEST_TIMEOUT_MS = 620000;

    // How long before the blocking dialog offers a way out. Twelve vhosts share
    // one php-cgi worker here, so a trivial save CAN sit behind someone else's
    // bulk send; without this the operator is stuck behind a modal with no Esc,
    // no outside-click and no cancel, and closing the tab is their only exit.
    var PROCESSING_ESCAPE_MS = 8000;

    // Whether the dialog on screen is OURS. Tracked with a flag rather than
    // asking SweetAlert, so this never depends on which of its introspection
    // helpers are public in a given release -- and so a toast that has already
    // replaced the dialog is never mistaken for it.
    var processingOpen  = false;
    var processingTimer = null;

    /** Blocking "working on it" dialog, for actions slow enough to look frozen. */
    function showProcessing(title, text) {
        if (!window.Swal) {
            return;
        }

        processingOpen = true;

        window.Swal.fire({
            title: title,
            text: text,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                window.Swal.showLoading();
            }
        });

        // allowEscapeKey/allowOutsideClick are not updatable in SweetAlert2, so
        // the escape hatch has to be a fresh dialog rather than an update. The
        // spinner moves into the body because showLoading() occupies the very
        // button we now need.
        processingTimer = window.setTimeout(function () {
            if (!processingOpen) {
                return;
            }

            window.Swal.fire({
                title: title,
                html: '<div class="mb-3"><i class="fa fa-spinner fa-spin fa-2x text-muted"></i></div>'
                    + '<div>' + esEscapeHtml(text || '') + '</div>'
                    + '<p class="small text-muted mt-3 mb-0">This is taking longer than usual, most likely because the server is busy. '
                    + 'You can close this message &mdash; the action will still finish.</p>',
                allowOutsideClick: true,
                allowEscapeKey: true,
                showConfirmButton: true,
                confirmButtonText: 'Close'
            }).then(function () {
                // Dismissed by the operator: stop claiming the dialog is ours,
                // so a later closeProcessing() cannot dismiss something else.
                processingOpen = false;
            });
        }, PROCESSING_ESCAPE_MS);
    }

    /** Dismiss the processing dialog, but only if it is still what is on screen. */
    function closeProcessing() {
        if (processingTimer) {
            window.clearTimeout(processingTimer);
            processingTimer = null;
        }

        if (window.Swal && processingOpen) {
            window.Swal.close();
        }

        processingOpen = false;
    }

    /**
     * Blocking error, mirroring the flash renderer in layouts/app.php.
     *
     * Deliberately NOT esNotify: a server-rendered error flash has an OK button
     * and no timer, while esNotify is a three-second corner toast. Routing
     * business rules ("You cannot demote your own account while logged in.")
     * through a toast puts the explanation in the corner while the operator is
     * looking at the modal they submitted from, and it is gone before they
     * turn their head.
     */
    function esBlockingError(title, text) {
        if (!window.Swal) {
            (window.console || {}).log && console.log('[error] ' + title + ' ' + (text || ''));
            return;
        }

        window.Swal.fire({
            icon: 'error',
            title: title || 'Error',
            text: text,
            confirmButtonText: 'OK'
        });
    }

    /**
     * The outcome genuinely is not known.
     *
     * Past 600s nginx returns a 504 while PHP keeps running to completion --
     * the app never emits output mid-request, so it never notices the aborted
     * connection. Reporting that as a failure is how an operator ends up
     * re-sending a batch of emails that were in fact still going out.
     */
    function esUnknownOutcome(opts) {
        opts = opts || {};

        if (!window.Swal) {
            return;
        }

        window.Swal.fire({
            icon: 'warning',
            title: opts.title || 'Outcome unknown',
            text: opts.text || 'The server took longer than it allows for one request. The action may still be running -- please check before trying again.',
            confirmButtonText: opts.linkUrl ? (opts.linkText || 'Check now') : 'OK'
        }).then(function (result) {
            if (opts.linkUrl && result && result.isConfirmed) {
                window.location.href = opts.linkUrl;
            }
        });
    }

    /**
     * POST a form over AJAX.
     *
     * @param {jQuery} $form
     * @param {object=} opts
     *   title          heading for the success toast / error dialog
     *   busy           { button, title, text } -- a `title` opts IN to the blocking dialog
     *   data           overrides $form.serialize()
     *   keepQuery      append window.location.search to the action
     *   onResponse     fn(res, $form) run on success AND on a message-bearing 4xx,
     *                  because a failure can still have changed the table
     *   onSuccess      fn(res, $form) success only
     *   unknownOutcome { title, text, linkUrl, linkText } for the 502/503/504 path
     */
    function esSubmitForm($form, opts) {
        opts = opts || {};

        var busy         = opts.busy || {};
        var $btn         = $form.find('[type="submit"]').first();
        var originalHtml = $btn.html();
        var context      = opts.context || 'The action could not be completed';

        $btn.prop('disabled', true);
        if (busy.button) {
            $btn.html('<i class="fa fa-spinner fa-spin me-1"></i> ' + esEscapeHtml(busy.button));
        }

        // Both indicators where the dialog is used, deliberately: the dialog is
        // what the operator actually notices, and the button state is what
        // remains correct if SweetAlert failed to load.
        if (busy.title) {
            showProcessing(busy.title, busy.text);
        }

        var url = opts.url || $form.attr('action');
        if (opts.keepQuery && window.location.search) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + window.location.search.replace(/^\?/, '');
        }

        function restore() {
            closeProcessing();
            $btn.prop('disabled', false);
            if (busy.button) {
                $btn.html(originalHtml);
            }
        }

        return $.ajax({
            url: url,
            type: 'POST',
            data: opts.data !== undefined ? opts.data : $form.serialize(),
            dataType: 'json',
            timeout: REQUEST_TIMEOUT_MS,
            // Marks the request as AJAX for CodeIgniter's isAJAX(), which is
            // what makes the controller answer with JSON instead of a redirect.
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CSRF_HASH }
        }).done(function (res) {
            // Close BEFORE esNotify. esNotify is itself a SweetAlert (a toast),
            // so closing afterwards would dismiss the very message it is meant
            // to show. This is why the close does not live in .always(), which
            // jQuery runs after done/fail.
            restore();

            if (typeof opts.onResponse === 'function') {
                opts.onResponse(res, $form);
            }

            esNotify('success', opts.title || 'Saved', res.message || 'Done.');

            if (typeof opts.onSuccess === 'function') {
                opts.onSuccess(res, $form);
            }
        }).fail(function (xhr, textStatus) {
            // Gateway and timeout FIRST, and the button is deliberately left
            // disabled afterwards: we do not know whether the work happened, so
            // re-arming the control invites a duplicate.
            if (textStatus === 'timeout' || xhr.status === 502 || xhr.status === 503 || xhr.status === 504) {
                closeProcessing();
                esUnknownOutcome(opts.unknownOutcome);
                return;
            }

            restore();

            // Status BEFORE body. AuthFilter's 401 envelope carries a `message`
            // key of its own, so testing the body first would show "session
            // expired" as a warning toast and never send the operator to login.
            if (xhr.status === 0 || xhr.status === 401 || xhr.status === 403) {
                esAjaxError(xhr, context);
                return;
            }

            var res = xhr.responseJSON;

            if (res && res.message) {
                // A 422 is a real, expected outcome and its text is written for
                // the operator -- so show it, and still repaint, because the
                // table may have changed even on a failure.
                if (typeof opts.onResponse === 'function') {
                    opts.onResponse(res, $form);
                }

                esBlockingError(opts.title || 'Error', res.message);
                return;
            }

            esAjaxError(xhr, context);
        });
    }

    /**
     * Apply the standard data-* reactions to a response.
     *
     * data-refresh is either a plain selector (swapped with res.html) or a JSON
     * map of selector -> payload key, for screens whose POST changes several
     * regions that are not contiguous in the DOM.
     */
    // Argument order matches how esSubmitForm invokes onResponse: (res, $form).
    // It read ($form, res) at first, so every declarative screen threw
    // "$form.attr is not a function" the moment a reply came back -- the POST
    // succeeded server-side and the page silently never repainted.
    function esApplyFormResult(res, $form) {
        var spec = $form.attr('data-refresh');

        if (spec) {
            if (spec.charAt(0) === '{') {
                var map = JSON.parse(spec);
                Object.keys(map).forEach(function (selector) {
                    if (res[map[selector]] !== undefined) {
                        $(selector).html(res[map[selector]]);
                    }
                });
            } else if (res.html !== undefined) {
                $(spec).html(res.html);
            }

            // Rows can arrive carrying date fields; this is guarded per input,
            // so re-running it is free.
            esInitDatePickers();

            // Anything that derives from the rows themselves -- a client-side
            // filter, a "showing N of M" counter, a select-all checkbox -- has
            // just been invalidated. Screens with such state listen for this
            // rather than each one re-implementing a post-swap hook.
            $(document).trigger('es:refreshed', [$form, res]);
        }
    }

    /**
     * Declarative binding, delegated on document so it survives the table body
     * being replaced.
     */
    $(document).on('submit', 'form.js-ajax', function (e) {
        var form  = this;
        var $form = $(form);

        // A form that still carries its own direct submit binding must never
        // also be driven from here. preventDefault() in that handler does not
        // stop propagation, so both would run: the direct one opens "Are you
        // sure?" while this one has already sent the request.
        if (form.dataset.esOwnHandler) {
            return;
        }

        // A native target="_blank" submit is a navigation the browser cannot
        // block; an XHR here would receive a document where it expects JSON.
        if (form.target && form.target !== '_self') {
            return;
        }

        // The exclusion can live on the submitter rather than the form --
        // settings/letter_templates' Preview button carries formaction +
        // formtarget while the form itself has no target at all.
        var submitter = e.originalEvent && e.originalEvent.submitter;
        if (submitter && (submitter.hasAttribute('formaction') || submitter.hasAttribute('formtarget'))) {
            return;
        }

        e.preventDefault();

        var d = form.dataset;

        var run = function () {
            esSubmitForm($form, {
                title: d.title || 'Saved',
                busy: {
                    button: d.busyButton || 'Working...',
                    title:  d.busyTitle,
                    text:   d.busyText
                },
                keepQuery: d.keepQuery === '1',
                onResponse: esApplyFormResult,
                onSuccess: function (res) {
                    if (d.closeModal && window.bootstrap) {
                        var el = document.querySelector(d.closeModal);
                        if (el) {
                            var modal = window.bootstrap.Modal.getInstance(el);
                            if (modal) { modal.hide(); }
                        }
                    }

                    if (d.clearOnSuccess) {
                        $form.find(d.clearOnSuccess).val('');
                    }

                    // "Add" forms live in a modal and are cleared today only by
                    // the page reload. reset() restores the markup defaults --
                    // which for a checkbox list means its `checked` attributes,
                    // not "everything off".
                    if (d.resetOnSuccess) {
                        form.reset();

                        // form.reset() restores the DOM defaults but fires no
                        // change event, so anything derived from the controls
                        // stays as the last save left it. On the Add User modal
                        // that meant the "N selected" badge, the footer summary
                        // and the admin/staff visibility of the whole permission
                        // block all described the user who had just been created
                        // -- until the modal was closed and reopened. Re-derive
                        // them from the values reset() has actually restored.
                        $(document).trigger('es:permissions-rendered');
                        $form.find('.js-role-select').trigger('change');
                    }

                    if (res.redirect_url) {
                        window.location.href = res.redirect_url;
                    }
                }
            });
        };

        if (d.confirmTitle && window.Swal) {
            window.Swal.fire({
                title: d.confirmTitle,
                text: d.confirmText,
                icon: d.confirmIcon || 'warning',
                showCancelButton: true,
                focusCancel: true,
                confirmButtonText: d.confirmButton || 'Yes, continue',
                cancelButtonText: 'Cancel'
            }).then(function (result) {
                if (result && result.isConfirmed) {
                    run();
                }
            });
            return;
        }

        run();
    });

    /**
     * The three "save a record, then show its letter" forms.
     *
     * These are `target="_blank"` native submits today, which is a navigation:
     * no pop-up blocker can suppress it however slow the server is. So the tab
     * is opened HERE, inside the click, and only pointed at the letter once the
     * save returns. Calling window.open() from the .done() continuation instead
     * would depend on Chrome's ~5s transient-activation window, and Dompdf on a
     * large batch -- queued behind whatever else is using the single php-cgi
     * worker -- comfortably outlasts it.
     *
     * If the save fails there is no letter, so the blank tab is closed again.
     */
    $(document).on('submit', 'form.js-ajax-pdf', function (e) {
        var form  = this;
        var $form = $(form);

        if (form.dataset.esOwnHandler) {
            return;
        }

        var submitter = e.originalEvent && e.originalEvent.submitter;
        if (submitter && (submitter.hasAttribute('formaction') || submitter.hasAttribute('formtarget'))) {
            return;
        }

        e.preventDefault();

        var d   = form.dataset;
        var tab = window.open('', '_blank');

        esSubmitForm($form, {
            title: d.title || 'Letter',
            busy: {
                button: d.busyButton || 'Generating...',
                title:  d.busyTitle,
                text:   d.busyText
            },
            context: 'The letter could not be generated',
            onSuccess: function (res) {
                if (res.pdf_url && tab) {
                    tab.location = res.pdf_url;
                } else if (res.pdf_url) {
                    // Tab creation itself was blocked -- give the operator a
                    // real click to open it with rather than losing the letter.
                    esBlockingError(d.title || 'Letter', 'The letter was saved but the new tab could not be opened. Use the history screen to print it.');
                }
            }
        }).fail(function () {
            if (tab) {
                tab.close();
            }
        });
    });

    window.esEscapeHtml  = esEscapeHtml;
    window.esNotify      = esNotify;
    window.esAjaxError   = esAjaxError;
    window.esAjaxSelect  = esAjaxSelect;
    window.esBindCheckAll = esBindCheckAll;
    window.esSyncCheckAll = esSyncCheckAll;
    window.esInitDatePickers = esInitDatePickers;
    window.esSubmitForm  = esSubmitForm;
    window.esApplyFormResult = esApplyFormResult;
    window.esBlockingError = esBlockingError;
    window.esUnknownOutcome = esUnknownOutcome;
    window.esShowProcessing = showProcessing;
    window.esCloseProcessing = closeProcessing;

    /* =====================================================================
       Motion
       ---------------------------------------------------------------------
       Timings are read FROM the stylesheet rather than duplicated here, so
       the motion scale has exactly one home. Retuning --es-dur-* in
       esection-theme.css retunes the JavaScript that has to stay in step
       with it -- otherwise a slower animation quietly starts committing
       before it has finished.
       ===================================================================== */

    var motionCache = {};

    /**
     * A CSS custom property in milliseconds.
     *
     * @param {string} name     e.g. '--es-dur-slow'
     * @param {number} fallback used when the property is missing or unparsable
     */
    function esMotionMs(name, fallback) {
        if (motionCache.hasOwnProperty(name)) {
            return motionCache[name];
        }

        var raw = '';

        try {
            raw = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        } catch (e) {
            raw = '';
        }

        var ms = fallback;

        if (/^[\d.]+ms$/.test(raw)) {
            ms = parseFloat(raw);
        } else if (/^[\d.]+s$/.test(raw)) {
            ms = parseFloat(raw) * 1000;
        }

        motionCache[name] = ms;

        return ms;
    }

    function esReducedMotion() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /* --- Navigation progress ---------------------------------------------
       A slim bar while the next page is being fetched. Honest feedback on a
       server-rendered app: it appears only when the browser is actually
       waiting, and it never blocks input, so a slow response can still be
       navigated away from.
       -------------------------------------------------------------------- */
    var $progress = null;
    var progressTimer = null;

    function progressBar() {
        if ($progress === null) {
            $progress = $('<div id="es-progress"></div>').appendTo('body');
        }

        return $progress;
    }

    function esProgressStart() {
        if (esReducedMotion()) {
            return;
        }

        progressBar().removeClass('es-progress--done').addClass('es-progress--on');
    }

    function esProgressDone() {
        if ($progress === null) {
            return;
        }

        $progress.removeClass('es-progress--on').addClass('es-progress--done');

        setTimeout(function () {
            $progress.removeClass('es-progress--done').css('width', 0);
        }, esMotionMs('--es-dur-base', 300));
    }

    /* --- Navigation feedback ----------------------------------------------
       The progress bar, and ONLY the progress bar.

       There used to be a page fade-out here as well: clicking a link dropped
       the current page to opacity 0 on the promise that another was arriving.
       When that promise turned out to be false the screen was left blank and
       unusable until a manual refresh.

       It was guarded twice -- first for cancelled clicks (the logout
       confirmation), then for downloads and a pagehide watchdog -- and it
       stranded the interface a third time anyway, because once beforeunload
       had fired the watchdog stood down, and a navigation that then failed or
       was cancelled left the fade in place with nothing to undo it.

       The list of ways a navigation can fail to complete is not enumerable,
       and the fade bought roughly 300ms of cosmetics on a page that was about
       to be replaced regardless. Removed rather than guarded a fourth time.

       What remains cannot strand anything: a 3px bar that is only ever added
       on top, so the worst case is a bar that lingers, not a page nobody can
       read.
       -------------------------------------------------------------------- */

    $(document).on('click', 'a[href]', function (e) {
        var $link = $(this);
        var href  = $link.attr('href') || '';

        // Cancelled by another handler -- a confirmation dialog, most often.
        // This is delegated on document, so handlers bound to the element have
        // already run and recorded it.
        if (e.isDefaultPrevented()) {
            return;
        }

        // Never a navigation of this page to begin with.
        if (e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }

        if ($link.is('[target], [download], [data-no-progress], [data-bs-toggle], [data-bs-dismiss]')) {
            return;
        }

        if (href === '' || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) {
            return;
        }

        esProgressStart();

        // A download leaves the page where it is, so nothing will ever clear
        // the bar; retire it on a timer instead. Same for any navigation that
        // simply does not happen.
        clearTimeout(progressTimer);
        progressTimer = setTimeout(esProgressDone, 4000);
    });

    $(document).on('submit', 'form', function (e) {
        if (e.isDefaultPrevented() || $(this).is('[target], [data-no-progress]')) {
            return;
        }

        esProgressStart();

        clearTimeout(progressTimer);
        progressTimer = setTimeout(esProgressDone, 4000);
    });

    $(window).on('pageshow', function (event) {
        clearTimeout(progressTimer);
        esProgressDone();

        if (event.originalEvent && event.originalEvent.persisted && $progress !== null) {
            $progress.removeClass('es-progress--on es-progress--done').css('width', 0);
        }
    });

    // A dialog opening over the page means the click that opened it was not a
    // navigation -- clear the bar now rather than waiting for the timer.
    $(document).on('show.bs.modal', function () {
        clearTimeout(progressTimer);
        esProgressDone();
    });

    window.esMotionMs      = esMotionMs;
    window.esReducedMotion = esReducedMotion;
    window.esProgressStart = esProgressStart;
    window.esProgressDone  = esProgressDone;

    $(function () { esInitDatePickers(); });
})(window, jQuery);
</script>
