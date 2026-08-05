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
    // The splash screen was removed deliberately. It was an opaque
    // full-viewport #0f172a overlay at z-index 99999 whose ONLY removal path
    // was a jQuery load handler -- any JS error above it left the entire app
    // behind a black screen with no recovery. On a server-rendered
    // multi-page app it also added ~800ms of dead time to every navigation.
    ?>
    <div id="wrapper">
        <!-- Left Sidebar Navigation -->
        <aside id="sidebar">
            <a href="<?= base_url('dashboard') ?>" class="sidebar-brand">
                <span class="brand-mark me-2">
                    <i class="fa fa-graduation-cap text-indigo"></i>
                </span>
                <span>ESection Portal</span>
            </a>

            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a href="<?= base_url('dashboard') ?>" data-label="Dashboard" class="nav-link <?= (uri_string() === 'dashboard' || uri_string() === '') ? 'active' : '' ?>">
                        <i class="fa fa-dashboard"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <?php if (page_access_granted('students_new')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('students/new') ?>" data-label="New Form" class="nav-link <?= (uri_string() === 'students/new') ? 'active' : '' ?>">
                        <i class="fa fa-plus-circle"></i>
                        <span class="link-text">New Form</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (page_access_granted('confirmations')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('confirmations') ?>" data-label="DD Confirmation" class="nav-link <?= (uri_string() === 'confirmations') ? 'active' : '' ?>">
                        <i class="fa fa-check-square-o"></i>
                        <span class="link-text">DD Confirmation</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (page_access_granted('universities')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('universities') ?>" data-label="University Directory" class="nav-link <?= (uri_string() === 'universities') ? 'active' : '' ?>">
                        <i class="fa fa-university"></i>
                        <span class="link-text">University Directory</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (page_access_granted('regularization')): ?>
                <li class="nav-item">
                    <a href="<?= base_url('regularization') ?>" data-label="Regularization" class="nav-link <?= (uri_string() === 'regularization') ? 'active' : '' ?>">
                        <i class="fa fa-file-text-o"></i>
                        <span class="link-text">Regularization</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php // One nav link covers both reminder pages -- shown if either is granted, since it's just a display nicety, not the enforcement point. ?>
                <?php if (page_access_granted('reminders_university') || page_access_granted('reminders_student')): ?>
                <li class="nav-item">
                    <a href="<?= base_url(page_access_granted('reminders_university') ? 'reminders/university' : 'reminders/student') ?>" data-label="Reminders" class="nav-link <?= (str_contains(uri_string(), 'reminders')) ? 'active' : '' ?>">
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

            <div class="p-3 border-top border-secondary border-opacity-25 mt-auto">
                <a href="<?= base_url('auth/logout') ?>" class="nav-link text-danger border-0 bg-transparent w-100 d-flex align-items-center logout-btn">
                    <i class="fa fa-sign-out me-2"></i>
                    <span class="link-text">Logout</span>
                </a>
            </div>
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
                    <div class="topbar-pill d-none d-md-inline-flex">
                        <i class="fa fa-clock-o me-2 text-indigo"></i>
                        <span class="text-muted font-monospace" id="live_clock"><?= date('H:i:s') ?></span>
                    </div>

                    <div class="topbar-pill">
                        <i class="fa fa-user-circle-o text-indigo"></i>
                        <span class="font-monospace fw-bold text-dark mx-2 d-none d-sm-inline"><?= esc(session()->get('username') ?? 'Staff') ?></span>
                        <span class="badge badge-glass-indigo text-uppercase ms-1 ms-sm-0" style="font-size: 0.65rem;"><?= esc(session()->get('role') ?? 'staff') ?></span>
                    </div>

                    <?php // Hidden below md: the sidebar drawer already carries a logout,
                          // and two logout affordances overflow a 375px topbar. ?>
                    <a href="<?= base_url('auth/logout') ?>" class="btn btn-sm btn-glass text-danger border-danger border-opacity-25 logout-btn d-none d-md-inline-flex">
                        <i class="fa fa-sign-out me-1"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Page Body -->
            <main class="page-body">
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>

    <!-- Offline Local JS -->
    <script src="<?= asset_url('assets/js/jquery.min.js') ?>"></script>
    <script src="<?= asset_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/select2/select2.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/flatpickr/flatpickr.min.js') ?>"></script>
    <script src="<?= asset_url('assets/vendor/sweetalert2/sweetalert2.min.js') ?>"></script>
    <script>
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

            // Live clock. 24h + 2-digit fields so the pill does not change
            // width every second, and matches the server-rendered seed.
            setInterval(function() {
                $('#live_clock').text(new Date().toLocaleTimeString('en-GB', {
                    hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
                }));
            }, 1000);

            // --- Flash messages ---------------------------------------------
            // Values are emitted as whole JSON literals, never hand-quoted: a
            // single apostrophe in a DB-derived message used to terminate the
            // JS string and take this entire <script> block down with it.
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
    </script>
    <?php // Shared AJAX/Select2 helpers. Must load before any view's
          // scripts section, which is what consumes them. ?>
    <?= $this->include('common/ajax_common_js') ?>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
