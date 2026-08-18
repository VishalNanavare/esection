<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card glass-card p-4 shadow-sm border-0">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="width: 64px; height: 64px; background: #e0e7ff; border: 1px solid #c7d2fe;">
                    <i class="fa fa-lock fa-2x text-gradient-indigo"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">Choose a New Password</h3>
                <?php // Read from the constants, not restated -- this line said
                      // "At least 8 characters" while the fields had already
                      // gained maxlength="10", so a pasted longer password was
                      // silently truncated with nothing on the page explaining why. ?>
                <p class="text-muted small">Between <?= (int) \App\Services\UserManagementService::MIN_PASSWORD_LENGTH ?> and <?= (int) \App\Services\UserManagementService::MAX_PASSWORD_LENGTH ?> characters.</p>
            </div>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-danger border-opacity-25 rounded-3 mb-3 small" role="alert">
                    <i class="fa fa-exclamation-circle me-1"></i> <?= esc(session()->getFlashdata('error')) ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/processResetPassword') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= esc($token) ?>">

                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">New Password</label>
                    <input type="password" name="password" class="form-control" minlength="<?= (int) \App\Services\UserManagementService::MIN_PASSWORD_LENGTH ?>" maxlength="<?= (int) \App\Services\UserManagementService::MAX_PASSWORD_LENGTH ?>" required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Confirm New Password</label>
                    <input type="password" name="password_confirm" class="form-control" minlength="<?= (int) \App\Services\UserManagementService::MIN_PASSWORD_LENGTH ?>" maxlength="<?= (int) \App\Services\UserManagementService::MAX_PASSWORD_LENGTH ?>" required>
                </div>
                <button type="submit" class="btn btn-indigo w-100">
                    <i class="fa fa-check me-2"></i> Reset Password
                </button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
