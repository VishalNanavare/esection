<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-paper-plane me-2 text-indigo"></i> Send Emails</h3>
                    <p class="text-muted small mb-0">Choose who to contact, review the exact list, then send.</p>
                </div>
                <div>
                    <a href="<?= base_url('bulk-email/log') ?>" class="btn btn-glass me-1"><i class="fa fa-list-alt me-1"></i> Sent Emails</a>
                    <a href="<?= base_url('settings/mail') ?>" class="btn btn-glass"><i class="fa fa-cog me-1"></i> Email Settings</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (! $mailReady): ?>
    <div class="row"><div class="col-12">
        <div class="glass-card p-4 mb-4 border border-warning border-opacity-25">
            <h6 class="fw-bold text-amber mb-1"><i class="fa fa-info-circle me-1"></i> Email is not set up yet</h6>
            <p class="text-muted small mb-0">
                Set the SMTP server and "from" address in
                <a href="<?= base_url('settings/mail') ?>">Settings &gt; Email</a> before sending.
            </p>
        </div>
    </div></div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <!-- Step 1: choose the audience and narrow the list -->
            <form action="<?= base_url('bulk-email') ?>" method="get" class="row g-3 mb-3 filter-panel">
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Send to</label>
                    <select name="audience" class="form-select" onchange="this.form.submit()">
                        <option value="university" <?= $audience === 'university' ? 'selected' : '' ?>>Universities (verification reminder)</option>
                        <option value="student" <?= $audience === 'student' ? 'selected' : '' ?>>Candidates (document reminder)</option>
                    </select>
                </div>

                <?php if ($audience === 'university'): ?>
                    <div class="col-md-5">
                        <label class="form-label text-secondary small fw-semibold">State (optional)</label>
                        <select name="state" class="form-select select2-ajax-state">
                            <?php if ($filters['state']): ?>
                                <option value="<?= esc($filters['state']) ?>" selected><?= esc($filters['state']) ?></option>
                            <?php else: ?>
                                <option value="">-- All States --</option>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-3">
                        <label class="form-label text-secondary small fw-semibold">Academic Year (optional)</label>
                        <select name="year" class="form-select select2-ajax-academic-year">
                            <?php if ($filters['year']): ?>
                                <option value="<?= esc($filters['year']) ?>" selected><?= esc($filters['year']) ?></option>
                            <?php else: ?>
                                <option value="">-- All Years --</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-secondary small fw-semibold">Stream (optional)</label>
                        <select name="stream" class="form-select select2-ajax-stream">
                            <?php if ($filters['stream']): ?>
                                <option value="<?= esc($filters['stream']) ?>" selected><?= esc($filters['stream']) ?></option>
                            <?php else: ?>
                                <option value="">-- All Streams --</option>
                            <?php endif; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <input type="hidden" name="preview" value="1">
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-indigo w-100"><i class="fa fa-search me-1"></i> Preview recipients</button>
                </div>
            </form>

            <p class="text-muted small mb-0">
                Message used: <strong class="text-dark"><?= esc($templateLabel) ?></strong>
                &mdash; <a href="<?= base_url('settings/mail') ?>">edit wording</a>
            </p>
        </div>
    </div>
</div>

<?php if ($preview !== null): ?>
    <div class="row">
        <div class="col-12">
            <div class="glass-card p-4 mb-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa fa-eye me-2 text-indigo"></i> Recipients (before sending)</h5>
                    <div>
                        <span class="badge badge-glass-emerald me-1"><?= count($preview['sendable']) ?> will be emailed</span>
                        <?php if (!empty($preview['skipped'])): ?>
                            <span class="badge badge-glass-amber"><?= count($preview['skipped']) ?> skipped</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($preview['truncated']): ?>
                    <div class="alert alert-warning small">
                        This list has more than <?= (int) $maxRecipients ?> matches. Only the first <?= (int) $maxRecipients ?>
                        are shown and will be sent &mdash; narrow the filter above to reach the rest in a second run.
                    </div>
                <?php endif; ?>

                <?php if (empty($preview['sendable'])): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fa fa-info-circle fs-1 mb-3 text-secondary d-block"></i>
                        No recipients with a usable email address match this filter.
                    </div>
                <?php else: ?>
                    <!-- Step 2: nothing is sent until THIS form is explicitly submitted. -->
                    <form action="<?= base_url('bulk-email/send') ?>" method="post" class="bulk-send-form"
                          data-count="<?= count($preview['sendable']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="audience" value="<?= esc($audience) ?>">
                        <input type="hidden" name="template_slug" value="<?= esc($slug) ?>">
                        <input type="hidden" name="state" value="<?= esc($filters['state']) ?>">
                        <input type="hidden" name="year" value="<?= esc($filters['year']) ?>">
                        <input type="hidden" name="stream" value="<?= esc($filters['stream']) ?>">

                        <div class="table-responsive mb-3" style="max-height: 360px; overflow-y: auto;">
                            <table class="table table-glass table-sm">
                                <thead>
                                    <tr><th>Name</th><th>Email</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview['sendable'] as $r): ?>
                                        <tr>
                                            <td><?= esc($r['name']) ?></td>
                                            <td class="small text-muted"><?= esc($r['email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-emerald px-4" <?= $mailReady ? '' : 'disabled' ?>>
                                <i class="fa fa-paper-plane me-1"></i> Confirm and send to <?= count($preview['sendable']) ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if (!empty($preview['skipped'])): ?>
                    <hr>
                    <h6 class="fw-bold text-dark mb-2">Skipped (no email sent)</h6>
                    <div class="table-responsive" style="max-height: 240px; overflow-y: auto;">
                        <table class="table table-glass table-sm">
                            <thead><tr><th>Name</th><th>Email on file</th><th>Reason</th></tr></thead>
                            <tbody>
                                <?php foreach ($preview['skipped'] as $s): ?>
                                    <tr>
                                        <td><?= esc($s['name']) ?></td>
                                        <td class="small text-muted"><?= esc($s['email'] ?: '-') ?></td>
                                        <td class="small text-muted"><?= esc($s['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_bulk_email_js') ?>
<?= $this->endSection() ?>
