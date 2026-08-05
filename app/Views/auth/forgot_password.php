<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card glass-card p-4 shadow-sm border-0">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="width: 64px; height: 64px; background: #e0e7ff; border: 1px solid #c7d2fe;">
                    <i class="fa fa-key fa-2x text-gradient-indigo"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">Forgot Password</h3>
                <p class="text-muted small">Enter your username or email and we'll send you a reset link.</p>
            </div>

            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success bg-success bg-opacity-10 text-success border-success border-opacity-25 rounded-3 mb-3 small" role="status">
                    <i class="fa fa-check-circle me-1"></i> <?= esc(session()->getFlashdata('success')) ?>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-danger border-opacity-25 rounded-3 mb-3 small" role="alert">
                    <i class="fa fa-exclamation-circle me-1"></i> <?= esc(session()->getFlashdata('error')) ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/processForgotPassword') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary border-opacity-25 text-muted"><i class="fa fa-user"></i></span>
                        <input type="text" name="identifier" class="form-control" placeholder="e.g. esection1 or you@idol.mu.ac.in" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-indigo w-100">
                    <i class="fa fa-paper-plane me-2"></i> Send Reset Link
                </button>
            </form>

            <div class="mt-3 text-center">
                <a href="<?= base_url('auth/login') ?>" class="small text-muted"><i class="fa fa-arrow-left me-1"></i> Back to login</a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
