<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-list-alt me-2 text-indigo"></i> Sent Emails</h3>
                    <p class="text-muted small mb-0">Every email the system has sent, with its delivery status.</p>
                </div>
                <div>
                    <span id="log_actions"><?= $this->include('bulk_email/_log_actions') ?></span>
                    <a href="<?= base_url('bulk-email') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Send Emails</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <form action="<?= base_url('bulk-email/log') ?>" method="get" class="row g-3 mb-4 filter-panel">
                <div class="col-md-4">
                    <label class="form-label text-secondary small fw-semibold">Status</label>
                    <select name="status" class="form-select" id="log_status_filter">
                        <?= $this->include('bulk_email/_log_status_filter') ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label text-secondary small fw-semibold">Search name or email</label>
                    <input type="text" name="q" class="form-control" value="<?= esc($search) ?>" placeholder="e.g. gujaratuniversity.ac.in">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-indigo w-100"><i class="fa fa-search me-1"></i> Search</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th>Date</th><th>To</th><th>Type</th><th>Subject</th><th>Status</th><th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="log_rows">
                        <?= $this->include('bulk_email/_log_rows') ?>
                    </tbody>
                </table>
            </div>

            <?php
                // Always-present host element. pagination_glass.php emits an
                // EMPTY STRING when there is only one page, so without this
                // wrapper there would be no node for an AJAX refresh to target
                // -- and a retry can change the page count.
            ?>
            <div id="log_pager_region">
                <?php if (!empty($pager)): ?>
                    <?= $pager->links('default', 'glass') ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
