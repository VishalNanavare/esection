<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<!-- Header Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="glass-card p-4 d-flex align-items-center justify-content-between">
            <div>
                <h2 class="fw-bold mb-1">Welcome, <span class="text-gradient-indigo"><?= session()->get('full_name') ?? 'E-Section Staff' ?></span></h2>
                <p class="text-muted mb-0">IDOL Eligibility & Document Verification Analytics System</p>
            </div>
            <div>
                <a href="<?= base_url('students/new') ?>" class="btn btn-indigo me-2">
                    <i class="fa fa-plus me-1"></i> New Eligibility Form
                </a>
                <a href="<?= base_url('confirmations') ?>" class="btn btn-emerald">
                    <i class="fa fa-check-square-o me-1"></i> DD Confirmation
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Metric Stat Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-semibold text-uppercase">Total Verification Cases</span>
                <div class="p-2 rounded-3 bg-indigo bg-opacity-10 text-indigo border border-indigo border-opacity-25">
                    <i class="fa fa-users fs-5"></i>
                </div>
            </div>
            <h2 class="display-6 fw-bold mb-1 text-dark"><?= number_format($total_students) ?></h2>
            <div class="small text-muted"><i class="fa fa-arrow-up me-1 text-emerald"></i> System Records</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-semibold text-uppercase">DD Confirmed</span>
                <div class="p-2 rounded-3 bg-emerald bg-opacity-10 text-emerald border border-emerald border-opacity-25">
                    <i class="fa fa-check-circle fs-5"></i>
                </div>
            </div>
            <h2 class="display-6 fw-bold mb-1 text-emerald"><?= number_format($total_confirmed) ?></h2>
            <div class="small text-muted"><i class="fa fa-check me-1 text-emerald"></i> Processed & Paid</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-semibold text-uppercase">Pending Approval</span>
                <div class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                    <i class="fa fa-clock-o fs-5"></i>
                </div>
            </div>
            <h2 class="display-6 fw-bold mb-1 text-warning"><?= number_format($total_pending) ?></h2>
            <div class="small text-muted"><i class="fa fa-exclamation-triangle me-1"></i> Awaiting Verification</div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <span class="text-muted small fw-semibold text-uppercase">Registered Universities</span>
                <div class="p-2 rounded-3 bg-info bg-opacity-10 text-info border border-info border-opacity-25">
                    <i class="fa fa-university fs-5"></i>
                </div>
            </div>
            <h2 class="display-6 fw-bold mb-1 text-dark"><?= number_format($total_colleges) ?></h2>
            <div class="small text-muted"><i class="fa fa-map-marker me-1"></i> Nationwide Directory</div>
        </div>
    </div>
</div>

<!-- Program Breakdown Table -->
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0"><i class="fa fa-list-alt me-2 text-gradient-indigo"></i> Verification Status by Academic Program</h4>
                <span class="badge badge-glass-indigo"><?= count($metrics) ?> Programs Tracked</span>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Sr. No.</th>
                            <th>Program / Academic Stream</th>
                            <th class="text-center">Total Students</th>
                            <th class="text-center">DD Confirmed</th>
                            <th class="text-center">Pending Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($metrics)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No student records found in database.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sr = 1; foreach ($metrics as $m): ?>
                                <tr>
                                    <td class="fw-semibold text-muted"><?= sprintf("%02d", $sr++) ?></td>
                                    <td class="fw-bold text-dark">
                                        <i class="fa fa-graduation-cap me-2 text-indigo"></i> <?= esc($m['stream']) ?>
                                    </td>
                                    <td class="text-center fw-semibold"><?= number_format($m['total']) ?></td>
                                    <td class="text-center">
                                        <span class="badge badge-glass-emerald"><i class="fa fa-check me-1"></i> <?= number_format($m['confirmed']) ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($m['pending'] > 0): ?>
                                            <span class="badge badge-glass-amber"><i class="fa fa-clock-o me-1"></i> <?= number_format($m['pending']) ?> Pending</span>
                                        <?php else: ?>
                                            <span class="badge badge-glass-emerald"><i class="fa fa-check-circle me-1"></i> Complete</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= base_url('confirmations?stream=' . urlencode($m['stream'])) ?>" class="btn btn-sm btn-glass">
                                            <i class="fa fa-search me-1"></i> Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
