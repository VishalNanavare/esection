<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'E-Section Verification Portal') ?></title>
    <!-- Offline CSS Assets -->
    <link rel="stylesheet" href="<?= asset_url('assets/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/font-awesome.min.css') ?>">
    <?php // Theme only -- the login page has no app chrome, so esection-shell.css
          // (sidebar/topbar/responsive) is deliberately not loaded here. ?>
    <link rel="stylesheet" href="<?= asset_url('assets/css/esection-theme.css') ?>">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 py-5">
    <div class="container">
        <?= $this->renderSection('content') ?>
    </div>

    <!-- Offline JS Assets -->
    <script src="<?= asset_url('assets/js/jquery.min.js') ?>"></script>
    <script src="<?= asset_url('assets/js/bootstrap.bundle.min.js') ?>"></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
