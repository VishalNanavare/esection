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
    <?php /*
      Splash.

      A splash used to live here and was removed for two sound reasons: its
      only removal path was a jQuery load handler, so any JS error above it
      trapped the app behind an opaque overlay with no recovery; and on a
      server-rendered multi-page app it added dead time to every navigation.

      This one cannot repeat either failure.

        - It is dismissed by a CSS animation with fill-mode forwards and NO
          JavaScript at all. If every script on the page fails to parse, the
          browser still runs the animation, so the splash still goes away.
        - #es-splash carries pointer-events: none from the start, so it never
          intercepts a click even while it is visible.
        - It is on the AUTH layout only -- the moment the application is
          entered -- so it costs the signed-in navigation nothing.
    */ ?>
    <div id="es-splash" aria-hidden="true">
        <div class="es-splash__stage">
            <div class="es-splash__ring">
                <span class="es-splash__mark"><i class="fa fa-shield"></i></span>
            </div>

            <div class="es-splash__word">E-Section Portal</div>
            <div class="es-splash__sub">Institute of Distance &amp; Open Learning</div>

            <div class="es-splash__bar"><span></span></div>
        </div>
    </div>


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
