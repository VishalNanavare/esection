<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'E-Section Verification System' ?></title>
    <!-- Offline CSS Assets -->
    <link rel="stylesheet" href="<?= base_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/font-awesome.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/select2/select2.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/glassmorphism.css') ?>">
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
            background: #0f172a;
            color: #94a3b8;
            transition: all 0.3s ease-in-out;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
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
            border-bottom: 1px solid #1e293b;
            color: #ffffff;
            font-weight: 700;
            font-size: 1.2rem;
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
            color: #94a3b8;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.925rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .sidebar-menu .nav-link:hover, .sidebar-menu .nav-link.active {
            background: rgba(79, 70, 229, 0.15);
            color: #818cf8;
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
            transition: all 0.3s ease-in-out;
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }

        .toggle-btn {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            border-radius: 0.375rem;
            padding: 0.4rem 0.65rem;
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
    <div id="wrapper">
        <!-- Left Sidebar Navigation -->
        <aside id="sidebar">
            <a href="<?= base_url('dashboard') ?>" class="sidebar-brand">
                <div class="bg-indigo p-2 rounded-3 me-2 d-inline-flex align-items-center justify-content-center" style="width: 34px; height: 34px; background: rgba(79, 70, 229, 0.3);">
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
                <a href="<?= base_url('auth/logout') ?>" class="nav-link text-danger d-flex align-items-center">
                    <i class="fa fa-sign-out me-2"></i>
                    <span class="link-text">Logout</span>
                </a>
            </div>
        </aside>

        <!-- Content Area -->
        <div id-="content-wrapper" id="content-wrapper">
            <!-- Top Navigation Header -->
            <header id="topbar">
                <div class="d-flex align-items-center gap-3">
                    <button class="toggle-btn" id="sidebar_toggle">
                        <i class="fa fa-list-alt"></i>
                    </button>
                    <div>
                        <h6 class="mb-0 fw-bold text-dark"><?= esc($title ?? 'IDOL Eligibility System') ?></h6>
                        <small class="text-muted" style="font-size: 0.75rem;">Institute of Distance & Open Learning</small>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center bg-light px-3 py-1.5 rounded-pill border">
                        <i class="fa fa-user-circle-o me-2 text-indigo"></i>
                        <span class="small font-monospace fw-bold text-dark me-2"><?= session()->get('username') ?? 'Staff' ?></span>
                        <span class="badge badge-glass-indigo text-uppercase" style="font-size: 0.65rem;"><?= session()->get('role') ?? 'staff' ?></span>
                    </div>

                    <a href="<?= base_url('auth/logout') ?>" class="btn btn-sm btn-glass text-danger border-danger border-opacity-25">
                        <i class="fa fa-sign-out me-1"></i> Logout
                    </a>
                </div>
            </header>

            <!-- Page Body -->
            <main class="page-body">
                <?php if (session()->getFlashdata('success')): ?>
                    <div class="alert alert-success bg-success bg-opacity-10 text-success border-success border-opacity-25 rounded-3 mb-4" role="alert">
                        <i class="fa fa-check-circle me-1"></i> <?= session()->getFlashdata('success') ?>
                    </div>
                <?php endif; ?>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-danger border-opacity-25 rounded-3 mb-4" role="alert">
                        <i class="fa fa-exclamation-circle me-1"></i> <?= session()->getFlashdata('error') ?>
                    </div>
                <?php endif; ?>

                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>

    <!-- Offline Local JS -->
    <script src="<?= base_url('assets/js/jquery.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <script src="<?= base_url('assets/vendor/select2/select2.min.js') ?>"></script>
    <script src="<?= base_url('assets/js/excel.js') ?>"></script>
    <script>
        $(document).ready(function() {
            $('#sidebar_toggle').on('click', function() {
                $('#sidebar').toggleClass('collapsed');
                $('#wrapper').toggleClass('sidebar-collapsed');
            });
        });
    </script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
