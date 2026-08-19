<!DOCTYPE html>
<?php
// The sidebar rail preference is read server-side from a cookie and stamped
// onto <html>, so the collapsed state is correct at first paint. Doing this
// in JS would flash an expanded sidebar on every navigation, because this is
// a multi-page server-rendered app.
$esRail = (($_COOKIE['es_rail'] ?? '0') === '1') ? ' class="es-rail"' : '';
?>
<html lang="en"<?= $esRail ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'E-Section Verification System') ?></title>
    <!-- Offline CSS Assets (asset_url appends a filemtime cache-buster) -->
    <?php // Explicit, rather than relying on the browser probing /favicon.ico:
          // that probe is skipped or cached as a miss often enough to look
          // broken, and it never carries a cache-busting version. ?>
    <link rel="icon" type="image/x-icon" href="<?= asset_url('favicon.ico') ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= asset_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/font-awesome.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/vendor/select2/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/vendor/flatpickr/flatpickr.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/esection-theme.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/esection-shell.css') ?>">
    <?php // SweetAlert2 is the .all build: it injects its own stylesheet at
          // runtime, so there is deliberately no sweetalert2 <link> here. ?>
</head>
<body>
    <?php
    // Still no splash on this layout, and for the original reasons: the old
    // one was an opaque full-viewport overlay whose ONLY removal path was a
    // jQuery load handler, so any JS error above it left the app behind a
    // black screen with no recovery -- and on a server-rendered multi-page
    // app it added dead time to every navigation.
    //
    // Signed-in pages get motion that cannot do either: content settles in
    // via .page-body's own CSS animation, which covers nothing and needs no
    // dismissing, and #es-progress reports real waiting during a navigation
    // without blocking input. The splash itself is on the auth layout only,
    // where entering the application is a moment rather than a hop between
    // pages.
    ?>
    <div id="wrapper">
        <!-- Left Sidebar Navigation -->
        <aside id="sidebar">
            <a href="<?= base_url('dashboard') ?>" class="sidebar-brand">
                <span class="brand-mark me-2">
                    <i class="fa fa-graduation-cap text-indigo"></i>
                </span>
                <span class="brand-text">ESection Portal</span>
            </a>

            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a href="<?= base_url('dashboard') ?>" data-label="Dashboard" class="nav-link <?= (uri_string() === 'dashboard' || uri_string() === '') ? 'active' : '' ?>">
                        <i class="fa fa-dashboard"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <?php
                    // Per-action now. These used to share one students_new gate,
                    // but Import has its own permission -- a clerk may be allowed
                    // to key candidates in one at a time without being handed a
                    // 2,000-row upload, and the nav has to reflect that.
                ?>
                <?php if (can('students.create')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('students/new') ?>" data-label="New Form" class="nav-link <?= (uri_string() === 'students/new') ? 'active' : '' ?>">
                        <i class="fa fa-plus-circle"></i>
                        <span class="link-text">New Form</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (feature_enabled('feature_import_enabled') && can('students.import')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('students/import') ?>" data-label="Import Excel" class="nav-link <?= str_starts_with(uri_string(), 'students/import') ? 'active' : '' ?>">
                        <i class="fa fa-file-excel-o"></i>
                        <span class="link-text">Import Excel</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (can('students.view')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('students/history') ?>" data-label="Batch History" class="nav-link <?= str_starts_with(uri_string(), 'students/history') || str_starts_with(uri_string(), 'students/batch') ? 'active' : '' ?>">
                        <i class="fa fa-list-alt"></i>
                        <span class="link-text">Batch History</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (can('confirmations.view')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('confirmations') ?>" data-label="DD Confirmation" class="nav-link <?= (uri_string() === 'confirmations') ? 'active' : '' ?>">
                        <i class="fa fa-check-square-o"></i>
                        <span class="link-text">DD Confirmation</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (can('universities.view')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('universities') ?>" data-label="University Directory" class="nav-link <?= (uri_string() === 'universities') ? 'active' : '' ?>">
                        <i class="fa fa-university"></i>
                        <span class="link-text">University Directory</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (can('regularization.view')): ?>
                <li class="nav-item">
                    <?php // /regularization IS the letter form and now requires
                          // regularization.create. Someone holding only .view
                          // still has history to read, so point them there
                          // rather than at a page that would refuse them. ?>
                    <a href="<?= base_url(can('regularization.create') ? 'regularization' : 'regularization/history') ?>" data-label="Regularization" class="nav-link <?= (str_starts_with(uri_string(), 'regularization')) ? 'active' : '' ?>">
                        <i class="fa fa-file-text-o"></i>
                        <span class="link-text">Regularization</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php // One nav link covers both reminder pages -- shown if either is granted, since it's just a display nicety, not the enforcement point. ?>
                <?php if (can_any(['reminders_university.view', 'reminders_student.view'])): ?>
                <li class="nav-item">
                    <a href="<?= base_url(can('reminders_university.view') ? 'reminders/university' : 'reminders/student') ?>" data-label="Reminders" class="nav-link <?= (str_contains(uri_string(), 'reminders')) ? 'active' : '' ?>">
                        <i class="fa fa-clock-o"></i>
                        <span class="link-text">Reminders</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (session()->get('role') === 'admin'): ?>
                <li class="nav-item">
                    <a href="<?= base_url('bulk-email') ?>" data-label="Send Emails" class="nav-link <?= (str_contains(uri_string(), 'bulk-email')) ? 'active' : '' ?>">
                        <i class="fa fa-paper-plane"></i>
                        <span class="link-text">Send Emails</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('settings') ?>" data-label="Settings" class="nav-link <?= (str_contains(uri_string(), 'settings')) ? 'active' : '' ?>">
                        <i class="fa fa-cog"></i>
                        <span class="link-text">Settings</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <?php // Logout lives in the topbar account menu only. Kept here as
                  // well, it was a mt-auto block that pushed the column past the
                  // viewport and put a scrollbar on the icon rail -- and the
                  // account menu is present at every breakpoint, so nothing is
                  // lost on mobile. ?>
        </aside>

        <?php // Dims and blocks the page while the mobile drawer is open. ?>
        <div id="sidebar_backdrop"></div>

        <!-- Content Area -->
        <div id="content-wrapper">
            <!-- Top Navigation Header -->
            <header id="topbar">
                <div class="d-flex align-items-center gap-3 topbar-title">
                    <button class="toggle-btn" id="sidebar_toggle" type="button"
                            aria-label="Toggle navigation" aria-controls="sidebar" aria-expanded="false">
                        <i class="fa fa-bars"></i>
                    </button>
                    <div class="text-truncate">
                        <h6 class="mb-0 fw-bold text-dark text-truncate"><?= esc($title ?? 'IDOL Eligibility System') ?></h6>
                        <small class="text-muted d-none d-sm-block" style="font-size: 0.75rem;">Institute of Distance &amp; Open Learning</small>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 gap-md-3">
                    <?php
                        // Day, date and a 12-hour clock, filled entirely by the
                        // browser -- deliberately NOT seeded from PHP.
                        //
                        // Config\App::$appTimezone is 'UTC' while this machine
                        // (and MySQL) run IST, so date() here is 5h30m behind the
                        // clock on the operator's wall. Seeding from PHP would
                        // print that wrong time until the first tick corrected
                        // it, and between 18:30 and midnight IST it would print
                        // the wrong DAY as well. esRenderClock() runs immediately
                        // on ready, so the fields are populated before the pill is
                        // ever noticed.
                        //
                        // The date is a separate span from the time because only
                        // the time changes each second.
                    ?>
                    <div class="topbar-pill d-none d-md-inline-flex">
                        <i class="fa fa-calendar-o me-2 text-indigo"></i>
                        <span class="text-muted" id="live_date"></span>
                        <span class="text-secondary opacity-50 mx-2">|</span>
                        <i class="fa fa-clock-o me-2 text-indigo"></i>
                        <span class="text-muted font-monospace" id="live_clock"></span>
                    </div>

                    <?php // One account control instead of a separate pill and logout
                          // button. The sidebar drawer keeps its own logout for the
                          // mobile layout, so this menu is the only affordance here. ?>
                    <?php
                        // data-bs-display="static" takes Popper out of the
                        // positioning entirely and lets the menu sit where the
                        // stylesheet puts it: directly under the toggle, right
                        // edges aligned via .dropdown-menu-end.
                        //
                        // Left to Popper it was flipping upward and covering the
                        // user pill. Popper decides that from the space it can
                        // measure inside the nearest clipping ancestors, and
                        // #topbar is a fixed-height, position:sticky flex row --
                        // so the space it sees below the button is the few pixels
                        // left inside the bar, not the whole page. In a header
                        // pinned to the top of the viewport, "below" is always
                        // the right answer, so there is nothing to compute.
                    ?>
                    <div class="dropdown">
                        <button class="topbar-pill border-0 bg-transparent d-flex align-items-center" type="button"
                                id="account_menu_btn" data-bs-toggle="dropdown"
                                data-bs-display="static" aria-expanded="false">
                            <i class="fa fa-user-circle-o text-indigo"></i>
                            <span class="font-monospace fw-bold text-dark mx-2 d-none d-sm-inline"><?= esc(session()->get('username') ?? 'Staff') ?></span>
                            <span class="badge badge-glass-indigo text-uppercase ms-1 ms-sm-0" style="font-size: 0.65rem;"><?= esc(session()->get('role') ?? 'staff') ?></span>
                            <i class="fa fa-caret-down text-muted ms-2"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end glass-card border-secondary border-opacity-25 mt-2"
                            aria-labelledby="account_menu_btn">
                            <li class="px-3 py-2 d-sm-none">
                                <div class="small text-muted">Signed in as</div>
                                <div class="font-monospace fw-bold text-dark"><?= esc(session()->get('username') ?? 'Staff') ?></div>
                            </li>
                            <li class="d-sm-none"><hr class="dropdown-divider"></li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center"
                                        data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                    <i class="fa fa-key me-2 text-indigo"></i> Reset Password
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a href="<?= base_url('auth/logout') ?>"
                                   class="dropdown-item d-flex align-items-center text-danger logout-btn">
                                    <i class="fa fa-sign-out me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Page Body -->
            <main class="page-body">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>

    <?php
        // Lives in the layout because the menu that opens it does. Uses the
        // shared js-ajax handler, so it saves without reloading and closes
        // itself; data-clear-on-success wipes all three boxes so no password is
        // left sitting in the DOM afterwards.
        //
        // The min/max attributes below are only the first line of defence --
        // they stop an honest typo before a round trip. The same bounds are
        // enforced in UserManagementService::assertPasswordPolicy(), which is
        // what actually protects the record, since anything client-side can be
        // edited away in devtools.
    ?>
    <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-secondary border-opacity-25">
                <div class="modal-header border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa fa-key me-2 text-indigo"></i> Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?= base_url('auth/changePassword') ?>" method="post" class="js-ajax"
                      id="reset_password_form"
                      data-title="Password"
                      data-close-modal="#resetPasswordModal"
                      data-clear-on-success="input[type=password]"
                      data-busy-button="Saving...">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <div class="alert bg-info bg-opacity-10 text-info border-info border-opacity-25 rounded-3 small mb-3" role="note">
                            <i class="fa fa-info-circle me-1"></i>
                            Your new password must be between
                            <strong><?= (int) \App\Services\UserManagementService::MIN_PASSWORD_LENGTH ?></strong> and
                            <strong><?= (int) \App\Services\UserManagementService::MAX_PASSWORD_LENGTH ?></strong> characters,
                            and must differ from your current one.
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold" for="rp_current">Current password</label>
                            <input type="password" name="current_password" id="rp_current" class="form-control"
                                   autocomplete="current-password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-secondary small fw-semibold" for="rp_new">New password</label>
                            <input type="password" name="new_password" id="rp_new" class="form-control"
                                   minlength="<?= (int) \App\Services\UserManagementService::MIN_PASSWORD_LENGTH ?>"
                                   maxlength="<?= (int) \App\Services\UserManagementService::MAX_PASSWORD_LENGTH ?>"
                                   autocomplete="new-password" required>
                        </div>
                        <div class="mb-1">
                            <label class="form-label text-secondary small fw-semibold" for="rp_confirm">Confirm new password</label>
                            <input type="password" name="confirm_password" id="rp_confirm" class="form-control"
                                   minlength="<?= (int) \App\Services\UserManagementService::MIN_PASSWORD_LENGTH ?>"
                                   maxlength="<?= (int) \App\Services\UserManagementService::MAX_PASSWORD_LENGTH ?>"
                                   autocomplete="new-password" required>
                            <div class="invalid-feedback" id="rp_confirm_error">The two new passwords do not match.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary border-opacity-25">
                        <button type="button" class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-indigo"><i class="fa fa-check me-1"></i> Change password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Offline Local JS -->
    <script src="<?= asset_url('assets/js/jquery.min.js') ?>"></script>
    <script src="<?= asset_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/select2/select2.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/flatpickr/flatpickr.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/sweetalert2/sweetalert2.min.js') ?>"></script>
    <script {csp-script-nonce}>
        // Some SweetAlert2 builds only export `Sweetalert2` (the UMD global)
        // and never assign `Swal`. Normalise here so every call site can rely
        // on window.Swal existing -- or being null, which each site checks.
        window.Swal = window.Swal || window.Sweetalert2 || null;

        // Hand button styling to the design system rather than fighting
        // SweetAlert2's injected CSS. Reassigning window.Swal keeps every
        // existing Swal.fire(...) call site working unchanged.
        if (window.Swal && typeof window.Swal.mixin === 'function') {
            window.Swal = window.Swal.mixin({
                buttonsStyling: false,
                customClass: {
                    popup:         'es-swal',
                    title:         'es-swal__title',
                    htmlContainer: 'es-swal__text',
                    confirmButton: 'btn btn-indigo',
                    cancelButton:  'btn btn-glass ms-2',
                    denyButton:    'btn btn-glass ms-2'
                }
            });
        }

        // --- Sidebar: desktop rail vs mobile drawer -------------------------
        // Vanilla and outside document.ready so the control responds as soon
        // as it exists, and so a later jQuery error cannot disable navigation.
        (function () {
            var LG     = window.matchMedia('(min-width: 992px)');
            var html   = document.documentElement;
            var body   = document.body;
            var toggle = document.getElementById('sidebar_toggle');
            var scrim  = document.getElementById('sidebar_backdrop');

            function closeDrawer() {
                body.classList.remove('es-drawer-open');
                if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
            }

            if (toggle) {
                toggle.addEventListener('click', function () {
                    if (LG.matches) {
                        // Desktop: collapse to an icon rail and remember it.
                        var railed = html.classList.toggle('es-rail');
                        document.cookie = 'es_rail=' + (railed ? '1' : '0') +
                                          ';path=/;max-age=31536000;SameSite=Lax;Secure';
                    } else {
                        // Mobile: open/close the drawer. Never persisted.
                        var open = body.classList.toggle('es-drawer-open');
                        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    }
                });
            }

            if (scrim) { scrim.addEventListener('click', closeDrawer); }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { closeDrawer(); }
            });

            // Crossing the breakpoint must not leave a stale drawer state.
            if (typeof LG.addEventListener === 'function') {
                LG.addEventListener('change', closeDrawer);
            } else if (typeof LG.addListener === 'function') {
                LG.addListener(closeDrawer);
            }
        })();

        $(document).ready(function() {
            // --- Logout is bound FIRST, deliberately. -----------------------
            // Anything that throws below (a missing library, a malformed flash
            // message) would otherwise abort this callback before the logout
            // handler was ever attached, silently breaking sign-out.
            $('.logout-btn').on('click', function(e) {
                // No dialog library? Fall through to the plain <a href> rather
                // than cancelling navigation and leaving the button inert.
                if (!window.Swal) {
                    return true;
                }

                e.preventDefault();
                var logoutUrl = this.href;

                Swal.fire({
                    title: 'Log Out of E-Section?',
                    text: 'Are you sure you want to exit your active staff session?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, Log Out',
                    cancelButtonText: 'Cancel'
                }).then(function(result) {
                    if (result && result.isConfirmed) {
                        window.location.href = logoutUrl;
                    }
                });
            });

            // Live clock, 12-hour with AM/PM. 2-digit fields so the pill does
            // not change width every second, and hourCycle 'h12' so midnight
            // reads 12:00 AM rather than 00:00 AM -- 'hour12: true' alone maps
            // midnight to hour 24 in some engines.
            //
            // en-GB is kept for the DATE only, because it gives day-month-year;
            // the time is formatted with en-US, which is what actually produces
            // an AM/PM suffix.
            function esRenderClock() {
                var now = new Date();

                $('#live_clock').text(now.toLocaleTimeString('en-US', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h12'
                }));

                // Cheap to recompute, but only written when it actually changes,
                // so the DOM is not touched 86,399 times a day for nothing.
                var dateText = now.toLocaleDateString('en-GB', {
                    weekday: 'short', day: '2-digit', month: 'short', year: 'numeric'
                });
                var $date = $('#live_date');
                if ($date.text() !== dateText) {
                    $date.text(dateText);
                }
            }

            esRenderClock();
            setInterval(esRenderClock, 1000);

            // --- Flash messages ---------------------------------------------
            // Values are emitted as whole JSON literals, never hand-quoted: a
            // single apostrophe in a DB-derived message used to terminate the
            // JS string and take this entire inline script block down with it.
            <?php if (session()->getFlashdata('success')): ?>
                if (window.Swal) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: <?= json_encode((string) session()->getFlashdata('success'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: <?= json_encode((string) session()->getFlashdata('error'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>,
                        confirmButtonText: 'OK'
                    });
                }
            <?php endif; ?>
        });

        // Replaces the two inline handler attributes this app used to carry:
        // a submit-the-form-on-change on the bulk-email audience select, and
        // a reload on the confirmation-history refresh button. A CSP nonce
        // authorises script ELEMENTS only and can never authorise a handler
        // attribute, so both had to move here or they would silently stop
        // working the moment the policy is enforced.
        //
        // Delegated from document so it does not matter whether the control
        // was in the initial HTML or added later by one of the AJAX views.
        document.addEventListener('change', function (e) {
            var el = e.target.closest && e.target.closest('[data-submit-on-change]');
            if (el && el.form) {
                el.form.submit();
            }
        });

        document.addEventListener('click', function (e) {
            if (e.target.closest && e.target.closest('[data-reload]')) {
                location.reload();
            }
        });
    </script>
    <?php // Shared AJAX/Select2 helpers. Must load before any view's
          // scripts section, which is what consumes them. ?>
    <?= $this->include('common/ajax_common_js') ?>
    <?php // The account menu and its Reset Password dialog live in this layout,
          // so their script does too -- every page carries them. ?>
    <?= $this->include('common/ajax_account_js') ?>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
