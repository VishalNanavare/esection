<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'E-Section Verification Portal') ?></title>
    <!-- Offline CSS Assets -->
    <?php // Explicit, rather than relying on the browser probing /favicon.ico:
          // that probe is skipped or cached as a miss often enough to look
          // broken, and it never carries a cache-busting version. ?>
    <link rel="icon" type="image/x-icon" href="<?= asset_url('favicon.ico') ?>">
    <link rel="shortcut icon" type="image/x-icon" href="<?= asset_url('favicon.ico') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/font-awesome.min.css') ?>">
    <?php // Theme only -- the login page has no app chrome, so esection-shell.css
          // (sidebar/topbar/responsive) is deliberately not loaded here. ?>
    <link rel="stylesheet" href="<?= asset_url('assets/css/esection-theme.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 py-5">
    <?php
    // No splash screen here, and there will not be one.
    //
    // layouts/app.php has carried a note for a while explaining why the last
    // one was deleted: its only removal path was a jQuery load handler, so any
    // JS error left the app behind an opaque overlay, AND on a server-rendered
    // multi-page app it added dead time to every navigation.
    //
    // I reintroduced it on this layout anyway, reasoning that signing in is a
    // moment rather than a hop between pages. That was wrong. The auth layout
    // is not rendered once -- it renders after every logout, after every failed
    // sign-in, after a password reset, and on every return to this page. So the
    // splash appeared in the middle of ordinary work, which is exactly the
    // "dead time on every navigation" the original note described.
    //
    // Feedback while signing in belongs on the control the operator just used:
    // the submit button spins (common/es_form_motion_js) and the page changes
    // when the server answers. Nothing needs to cover the screen to say so.
    ?>

    <div class="container">
        <?= $this->renderSection('content') ?>
    </div>

    <!-- Offline JS Assets -->
    <script src="<?= asset_url('assets/js/jquery.min.js') ?>"></script>
    <script src="<?= asset_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <?php // Dependency-free (jQuery only) -- this layout deliberately carries
          // no Select2/SweetAlert, so it cannot include ajax_common_js. Without
          // this, pressing Sign In gave no feedback at all. ?>
    <?= $this->include('common/es_form_motion_js') ?>
    <?= $this->include('common/es_password_toggle_js') ?>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
