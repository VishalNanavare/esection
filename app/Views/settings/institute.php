<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-university me-2 text-indigo"></i> Institute Details</h3>
                    <p class="text-muted small mb-0">These details appear on every generated letter — update them here instead of asking a developer.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>

            <form id="institute_form" action="<?= base_url('settings/institute/store') ?>" method="post" enctype="multipart/form-data" data-es-own-handler="1">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label text-secondary small fw-semibold">Institute Name</label>
                        <input type="text" name="institute_name" class="form-control" value="<?= esc($details['institute_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small fw-semibold">Contact Details</label>
                        <input type="text" name="institute_contact" class="form-control" value="<?= esc($details['institute_contact'] ?? '') ?>" placeholder="Phone / email">
                    </div>
                    <div class="col-12">
                        <label class="form-label text-secondary small fw-semibold">Parent University Name</label>
                        <input type="text" name="institute_university_title" class="form-control" value="<?= esc($details['institute_university_title'] ?? '') ?>" placeholder="UNIVERSITY OF MUMBAI">
                        <small class="text-muted">The university-level heading printed above the institute name on every letter. Leave blank to keep printing "UNIVERSITY OF MUMBAI".</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label text-secondary small fw-semibold">Full Postal Address</label>
                        <textarea name="institute_address" class="form-control" rows="3"><?= esc($details['institute_address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Signatory Name</label>
                        <input type="text" name="institute_signatory_name" class="form-control" value="<?= esc($details['institute_signatory_name'] ?? '') ?>" placeholder="e.g. Dr. Jane Doe">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Signatory Designation</label>
                        <input type="text" name="institute_signatory_designation" class="form-control" value="<?= esc($details['institute_signatory_designation'] ?? '') ?>" placeholder="e.g. Director">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small fw-semibold">Signature Space (blank lines)</label>
                        <input type="number" name="institute_signature_space_lines" id="signature_space_input" class="form-control"
                               value="<?= esc($details['institute_signature_space_lines'] ?? '') ?>"
                               min="<?= (int) $signatureSpaceMin ?>" max="<?= (int) $signatureSpaceMax ?>" placeholder="Default">
                        <div id="signature_space_error" class="small text-danger mt-1" style="display:none;"></div>
                        <small class="text-muted">Blank lines above the signature in every letter, to leave room to sign (<?= (int) $signatureSpaceMin ?>&ndash;<?= (int) $signatureSpaceMax ?>). Leave blank to keep the default spacing.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Logo</label>
                        <div class="mb-2">
                            <img id="logo_preview" src="<?= !empty($details['institute_logo_path']) ? esc(base_url($details['institute_logo_path']), 'attr') : '' ?>"
                                 alt="Logo preview" style="max-height:60px; <?= empty($details['institute_logo_path']) ? 'display:none;' : '' ?>">
                        </div>
                        <input type="file" name="logo" id="logo_input" class="form-control" accept="image/png,image/jpeg"
                               data-max-size-kb="<?= (int) $maxSizeKb ?>" data-required-width="<?= (int) $logoWidth ?>" data-required-height="<?= (int) $logoHeight ?>">
                        <div id="logo_error" class="small text-danger mt-1" style="display:none;"></div>
                        <small class="text-muted">PNG or JPEG, exactly <?= (int) $logoWidth ?>&times;<?= (int) $logoHeight ?>px, up to <?= (int) ($maxSizeKb / 1024) ?>MB. Leave blank to keep the current logo.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Letterhead</label>
                        <div class="mb-2">
                            <img id="letterhead_preview" src="<?= !empty($details['institute_letterhead_path']) ? esc(base_url($details['institute_letterhead_path']), 'attr') : '' ?>"
                                 alt="Letterhead preview" style="max-height:60px; <?= empty($details['institute_letterhead_path']) ? 'display:none;' : '' ?>">
                        </div>
                        <input type="file" name="letterhead" id="letterhead_input" class="form-control" accept="image/png,image/jpeg"
                               data-max-size-kb="<?= (int) $maxSizeKb ?>" data-required-width="<?= (int) $letterheadWidth ?>" data-required-height="<?= (int) $letterheadHeight ?>">
                        <div id="letterhead_error" class="small text-danger mt-1" style="display:none;"></div>
                        <small class="text-muted">PNG or JPEG, exactly <?= (int) $letterheadWidth ?>&times;<?= (int) $letterheadHeight ?>px, up to <?= (int) ($maxSizeKb / 1024) ?>MB. Leave blank to keep the current letterhead.</small>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-indigo" id="institute_submit_btn"><i class="fa fa-check me-1"></i> Save Institute Details</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_institute_js') ?>
<?= $this->endSection() ?>
