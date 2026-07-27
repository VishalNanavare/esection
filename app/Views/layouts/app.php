<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'E-Section Verification System') ?></title>
    <!-- Offline CSS Assets (asset_url appends a filemtime cache-buster) -->
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/font-awesome.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/vendor/select2/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/glassmorphism.css') ?>">
    <?php // SweetAlert2 is the .all build: it injects its own stylesheet at
          // runtime, so there is deliberately no sweetalert2 <link> here. ?>
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
            --topbar-height: 64px;
        }

        #wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Fixed Left Sidebar */
        #sidebar {
            width: var(--sidebar-width);
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            color: #475569;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
        }

        #sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar-brand {
            height: var(--topbar-height);
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            color: #0f172a;
            font-weight: 700;
            font-size: 1.15rem;
            text-decoration: none;
        }

        .sidebar-menu {
            list-style: none;
            padding: 1rem 0.75rem;
            margin: 0;
            flex-grow: 1;
        }

        .sidebar-menu .nav-item {
            margin-bottom: 0.35rem;
        }

        .sidebar-menu .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #64748b;
            border-radius: 0.6rem;
            font-weight: 500;
            font-size: 0.925rem;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
        }

        .sidebar-menu .nav-link:hover {
            background: #f1f5f9;
            color: #4f46e5;
        }

        .sidebar-menu .nav-link.active {
            background: #e0e7ff;
            color: #4f46e5;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.12);
        }

        .sidebar-menu .nav-link i {
            font-size: 1.1rem;
            margin-right: 0.85rem;
            width: 20px;
            text-align: center;
        }

        #sidebar.collapsed .sidebar-brand span,
        #sidebar.collapsed .sidebar-menu .link-text {
            display: none;
        }

        #sidebar.collapsed .sidebar-menu .nav-link i {
            margin-right: 0;
        }

        /* Main Content Wrapper */
        #content-wrapper {
            flex-grow: 1;
            margin-left: var(--sidebar-width);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        #wrapper.sidebar-collapsed #content-wrapper {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Top Header Bar */
        #topbar {
            height: var(--topbar-height);
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 999;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }

        .toggle-btn {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #475569;
            border-radius: 0.5rem;
            padding: 0.45rem 0.7rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .toggle-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .page-body {
            padding: 1.75rem;
            flex-grow: 1;
        }
    </style>
</head>
<body>
    <!-- Splash Screen Loader -->
    <div id="splash_screen">
        <div class="splash-logo">
            <i class="fa fa-graduation-cap fs-2 text-indigo"></i>
        </div>
        <h5 class="fw-bold mt-3 text-white mb-0">IDOL E-SECTION PORTAL</h5>
        <p class="small text-secondary mb-0">Loading System Engine...</p>
        <div class="splash-progress">
            <div class="splash-progress-bar"></div>
        </div>
    </div>

    <div id="wrapper">
        <!-- Left Sidebar Navigation -->
        <aside id="sidebar">
            <a href="<?= base_url('dashboard') ?>" class="sidebar-brand">
                <div class="p-2 rounded-3 me-2 d-inline-flex align-items-center justify-content-center" style="width: 36px; height: 36px; background: #e0e7ff; border: 1px solid #c7d2fe;">
                    <i class="fa fa-graduation-cap text-indigo fs-5"></i>
                </div>
                <span>ESection Portal</span>
            </a>

            <ul class="sidebar-menu">
                <li class="nav-item">
                    <a href="<?= base_url('dashboard') ?>" class="nav-link <?= (uri_string() === 'dashboard' || uri_string() === '') ? 'active' : '' ?>">
                        <i class="fa fa-dashboard"></i>
                        <span class="link-text">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('students/new') ?>" class="nav-link <?= (uri_string() === 'students/new') ? 'active' : '' ?>">
                        <i class="fa fa-plus-circle"></i>
                        <span class="link-text">New Form</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('confirmations') ?>" class="nav-link <?= (uri_string() === 'confirmations') ? 'active' : '' ?>">
                        <i class="fa fa-check-square-o"></i>
                        <span class="link-text">DD Confirmation</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('universities') ?>" class="nav-link <?= (uri_string() === 'universities') ? 'active' : '' ?>">
                        <i class="fa fa-university"></i>
                        <span class="link-text">University Directory</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('regularization') ?>" class="nav-link <?= (uri_string() === 'regularization') ? 'active' : '' ?>">
                        <i class="fa fa-file-text-o"></i>
                        <span class="link-text">Regularization</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="<?= base_url('reminders/university') ?>" class="nav-link <?= (str_contains(uri_string(), 'reminders')) ? 'active' : '' ?>">
                        <i class="fa fa-clock-o"></i>
                        <span class="link-text">Reminders</span>
                    </a>
                </li>
            </ul>

            <div class="p-3 border-top border-secondary border-opacity-25 mt-auto">
                <a href="<?= base_url('auth/logout') ?>" class="nav-link text-danger border-0 bg-transparent w-100 d-flex align-items-center logout-btn">
                    <i class="fa fa-sign-out me-2"></i>
                    <span class="link-text">Logout</span>
                </a>
            </div>
        </aside>

        <!-- Content Area -->
        <div id="content-wrapper">
            <!-- Top Navigation Header -->
            <header id="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="toggle-btn" id="sidebar_toggle">
                        <i class="fa fa-bars"></i>
                    </button>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><?= esc($title ?? 'IDOL Eligibility System') ?></h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Institute of Distance & Open Learning</small>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-none d-md-flex align-items-center bg-light px-3 py-1.5 rounded-pill border">
                        <i class="fa fa-clock-o me-2 text-indigo"></i>
                        <span class="small text-muted font-monospace" id="live_clock"><?= date('H:i:s') ?></span>
                    </div>

                    <div class="d-flex align-items-center bg-light px-3 py-1.5 rounded-pill border">
                        <i class="fa fa-user-circle-o me-2 text-indigo"></i>
                        <span class="small font-monospace fw-bold text-dark me-2"><?= session()->get('username') ?? 'Staff' ?></span>
                        <span class="badge badge-glass-indigo text-uppercase" style="font-size: 0.65rem;"><?= session()->get('role') ?? 'staff' ?></span>
                    </div>

                    <a href="<?= base_url('auth/logout') ?>" class="btn btn-sm btn-glass text-danger border-danger border-opacity-25 logout-btn">
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

        $(window).on('load', function() {
            setTimeout(function() {
                $('#splash_screen').addClass('fade-out');
            }, 300);
        });

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

            // Live Clock
            setInterval(function() {
                var now = new Date();
                $('#live_clock').text(now.toLocaleTimeString());
            }, 1000);

            // Sidebar Toggle
            $('#sidebar_toggle').on('click', function() {
                $('#sidebar').toggleClass('collapsed');
                $('#wrapper').toggleClass('sidebar-collapsed');
            });

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
    <?= $this->renderSection('scripts') ?>
</body>
</html>
