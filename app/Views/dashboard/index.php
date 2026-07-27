<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<!-- Header Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="glass-card p-4 page-header mb-0">
            <div>
                <h2 class="fw-bold mb-1">Welcome, <span class="text-indigo"><?= esc(session()->get('full_name') ?? 'E-Section Staff') ?></span></h2>
                <p class="text-muted mb-0">IDOL Eligibility &amp; Document Verification Analytics System</p>
            </div>
            <div class="d-grid d-sm-flex gap-2">
                <a href="<?= base_url('students/new') ?>" class="btn btn-indigo">
                    <i class="fa fa-plus me-1"></i> New Eligibility Form
                </a>
                <a href="<?= base_url('confirmations') ?>" class="btn btn-emerald">
                    <i class="fa fa-check-square-o me-1"></i> DD Confirmation
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// One KPI tile shape, driven by data. This was four copies of an identical
// ten-line block. The colour classes now actually resolve -- text-indigo,
// bg-emerald and friends were undefined, so every tile rendered grey.
$statCards = [
    [
        'label' => 'Total Verification Cases',
        'value' => $total_students,
        'icon'  => 'fa-users',
        'tone'  => 'indigo',
        'meta'  => '<i class="fa fa-arrow-up me-1 text-emerald"></i> System records',
    ],
    [
        'label' => 'DD Confirmed',
        'value' => $total_confirmed,
        'icon'  => 'fa-check-circle',
        'tone'  => 'emerald',
        'meta'  => '<i class="fa fa-check me-1 text-emerald"></i> Processed &amp; paid',
    ],
    [
        'label' => 'Pending Approval',
        'value' => $total_pending,
        'icon'  => 'fa-clock-o',
        'tone'  => 'warning',
        'meta'  => '<i class="fa fa-exclamation-triangle me-1 text-amber"></i> Awaiting verification',
    ],
    [
        'label' => 'Registered Universities',
        'value' => $total_colleges,
        'icon'  => 'fa-university',
        'tone'  => 'info',
        'meta'  => '<i class="fa fa-map-marker me-1 text-muted"></i> Nationwide directory',
    ],
];
?>
<!-- Metric Stat Cards -->
<div class="row g-3 g-md-4 mb-4">
    <?php foreach ($statCards as $card): ?>
        <?php $tone = $card['tone']; ?>
        <div class="col-6 col-xl-3">
            <div class="glass-card stat-card h-100">
                <div class="stat-card__head">
                    <span class="stat-card__label"><?= esc($card['label']) ?></span>
                    <span class="stat-card__icon bg-<?= $tone ?> bg-opacity-10 text-<?= $tone ?> border-<?= $tone ?> border-opacity-25">
                        <i class="fa <?= $tone === 'warning' ? 'fa-clock-o' : esc($card['icon']) ?>"></i>
                    </span>
                </div>
                <p class="stat-card__value text-<?= $tone === 'indigo' || $tone === 'info' ? 'dark' : $tone ?>">
                    <?= number_format((int) $card['value']) ?>
                </p>
                <div class="stat-card__meta"><?= $card['meta'] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Program Breakdown Table -->
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold mb-0"><i class="fa fa-list-alt me-2 text-indigo"></i> Verification Status by Academic Program</h4>
                <span class="badge badge-glass-indigo"><?= count($metrics) ?> Programs Tracked</span>
            </div>

            <div class="table-responsive">
                <table class="table table-glass table-sticky-id">
                    <thead>
                        <tr>
                            <th class="col-sr">Sr. No.</th>
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
