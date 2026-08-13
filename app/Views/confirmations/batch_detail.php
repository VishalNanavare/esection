<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-check-square-o me-2 text-indigo"></i> Confirmation Batch #<?= esc($arraySpace) ?></h3>
                    <p class="text-muted small mb-0">Full eligibility confirmation detail for this batch.</p>
                </div>
                <div>
                    <?php if (can('confirmations.print')): ?>
                        <a href="<?= base_url('confirmations/eligibilityPdf/' . $arraySpace) ?>" target="_blank" class="btn btn-emerald me-1"><i class="fa fa-file-pdf-o me-1"></i> Eligibility Letter</a>
                    <?php endif; ?>
                    <a href="<?= base_url('confirmations/history') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to History</a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-glass">
                    <thead>
                        <tr>
                            <th class="col-sr">#</th>
                            <th>Candidate</th>
                            <th>University</th>
                            <th>Case No.</th>
                            <th>Mig/TC</th>
                            <th>Pass/Dgr</th>
                            <th>Statement of Marks</th>
                            <th>Letter No./Dated</th>
                            <th>Remark</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="confirmation_batch_rows">
                        <?= $this->include('confirmations/_batch_rows') ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_confirmations_history_js') ?>
<?= $this->endSection() ?>
