<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-file-text-o me-2 text-indigo"></i> Letter Templates</h3>
                    <p class="text-muted small mb-0">Edit the subject, main paragraph and closing sentence printed on each of the 4 letter types. Preview shows a real PDF built from your unsaved changes -- nothing is written until you press Save.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>

            <form id="footer_form" action="<?= base_url('settings/letter-templates/footer/store') ?>" method="post" class="row g-3 align-items-end" data-es-own-handler="1">
                <?= csrf_field() ?>
                <div class="col-md-8">
                    <label class="form-label text-secondary small fw-semibold">Department Name (shown under the signature on every letter)</label>
                    <input type="text" name="footer_department" class="form-control" value="<?= esc($footerDepartment) ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-indigo w-100"><i class="fa fa-check me-1"></i> Save Department Name</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($templates as $slug => $tpl): ?>
    <div class="row">
        <div class="col-12">
            <div class="glass-card p-4 mb-4">
                <h5 class="fw-bold text-dark mb-1"><?= esc($tpl['label']) ?></h5>
                <p class="small text-muted mb-3">
                    Available placeholders:
                    <?php foreach ($tpl['tokens'] as $token): ?>
                        <code>{<?= esc($token) ?>}</code>
                    <?php endforeach; ?>
                </p>

                <form id="letter_form_<?= esc($slug) ?>" action="<?= base_url('settings/letter-templates/' . $slug . '/store') ?>" method="post" data-slug="<?= esc($slug) ?>" data-es-own-handler="1">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Subject Line</label>
                            <input type="text" name="subject" class="form-control" value="<?= esc($tpl['fields']['subject']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Main Paragraph</label>
                            <textarea name="body" class="form-control" rows="4" style="font-family: monospace;"><?= esc($tpl['fields']['body']) ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label text-secondary small fw-semibold">Closing Sentence</label>
                            <textarea name="closing" class="form-control" rows="2" style="font-family: monospace;"><?= esc($tpl['fields']['closing']) ?></textarea>
                        </div>
                    </div>

                    <div class="mt-3 text-end">
                        <?php // formaction/formtarget override this button's own action/target only --
                              // one form serves both an AJAX save (default button) and a real
                              // new-tab-opening submit carrying the live, unsaved field values. ?>
                        <button type="submit"
                                formaction="<?= base_url('settings/letter-templates/' . $slug . '/preview') ?>"
                                formtarget="_blank"
                                class="btn btn-glass me-2">
                            <i class="fa fa-file-pdf-o me-1"></i> Preview PDF
                        </button>
                        <button type="submit" class="btn btn-indigo letter-save-btn">
                            <i class="fa fa-check me-1"></i> Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_letter_templates_js') ?>
<?= $this->endSection() ?>
