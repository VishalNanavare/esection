<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card glass-card p-4 shadow-sm border-0">
            <div class="text-center mb-4">
                <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle mb-3" style="width: 64px; height: 64px; background: #e0e7ff; border: 1px solid #c7d2fe;">
                    <i class="fa fa-shield fa-2x text-gradient-indigo"></i>
                </div>
                <h3 class="fw-bold mb-1 text-dark">E-Section Portal</h3>
                <p class="text-muted small">Institute of Distance & Open Learning (IDOL)</p>
            </div>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-danger border-opacity-25 rounded-3 mb-3 small" role="alert">
                    <i class="fa fa-exclamation-circle me-1"></i> <?= session()->getFlashdata('error') ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('auth/processLogin') ?>" method="post">
                <?= csrf_field() ?>
                
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary border-opacity-25 text-muted"><i class="fa fa-user"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="e.g. esection1 or admin" required autofocus>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary border-opacity-25 text-muted"><i class="fa fa-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-indigo w-100 py-2.5">
                    <i class="fa fa-sign-in me-2"></i> Log In to Dashboard
                </button>
            </form>

            <div class="mt-4 text-center">
                <small class="text-muted" style="font-size: 0.75rem;">
                    &copy; <?= date('Y') ?> IDOL Eligibility Section. All rights reserved.
                </small>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
