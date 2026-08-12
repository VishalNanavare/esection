<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-envelope me-2 text-indigo"></i> Email</h3>
                    <p class="text-muted small mb-0">Connect your own mail account, and edit the wording the system sends.</p>
                </div>
                <a href="<?= base_url('settings') ?>" class="btn btn-glass"><i class="fa fa-arrow-left me-1"></i> Back to Settings</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- SMTP connection -->
    <div class="col-lg-7">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-dark mb-3"><i class="fa fa-plug me-2 text-indigo"></i> Mail account (SMTP)</h5>
            <?php
                // Own handler: the reply repaints the Status block AND flips
                // the "saved" password badge, which the declarative binding has
                // no vocabulary for. data-es-own-handler keeps the shared
                // handler from also firing if js-ajax is ever added here.
            ?>
            <form action="<?= base_url('settings/mail/store') ?>" method="post"
                  id="mail_smtp_form" data-es-own-handler="1">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label text-secondary small fw-semibold">SMTP server</label>
                        <input type="text" name="mail_smtp_host" class="form-control" placeholder="e.g. smtp.gmail.com"
                               value="<?= esc($settings['mail_smtp_host']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small fw-semibold">Port</label>
                        <input type="number" name="mail_smtp_port" class="form-control" min="1" max="65535"
                               value="<?= (int) $settings['mail_smtp_port'] ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Username</label>
                        <input type="text" name="mail_smtp_user" class="form-control" autocomplete="off"
                               value="<?= esc($settings['mail_smtp_user']) ?>">
                        <small class="text-muted">The login your mail provider expects &mdash; for Gmail and most
                            providers this is the full email address, not a display name. (Some services use a fixed
                            username instead, e.g. SendGrid uses <code>apikey</code>.)</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">
                            Password
                            <span id="mail_password_badge">
                                <?php if ($settings['password_configured']): ?>
                                    <span class="badge badge-glass-emerald ms-1">saved</span>
                                <?php endif; ?>
                            </span>
                        </label>
                        <input type="password" name="mail_smtp_password" id="mail_smtp_password" class="form-control" autocomplete="new-password"
                               placeholder="<?= $settings['password_configured'] ? 'Leave blank to keep current' : '' ?>">
                        <small class="text-muted">Most providers require an app-specific password rather than the
                            account's normal one &mdash; Gmail and Google Workspace both do. Any spaces are removed
                            automatically, so a code shown in groups can be pasted as-is.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small fw-semibold">Encryption</label>
                        <select name="mail_smtp_crypto" class="form-select">
                            <?php foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'None'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $settings['mail_smtp_crypto'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small fw-semibold">From address</label>
                        <input type="email" name="mail_from_email" class="form-control"
                               value="<?= esc($settings['mail_from_email']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-secondary small fw-semibold">From name</label>
                        <input type="text" name="mail_from_name" class="form-control" placeholder="IDOL Eligibility Section"
                               value="<?= esc($settings['mail_from_name']) ?>">
                    </div>

                    <div class="col-12"><hr class="my-1"></div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Send in batches of</label>
                        <input type="number" name="mail_batch_size" class="form-control" min="1" max="500"
                               value="<?= (int) $settings['mail_batch_size'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-secondary small fw-semibold">Pause between batches (seconds)</label>
                        <input type="number" name="mail_batch_pause" class="form-control" min="0" max="300"
                               value="<?= (int) $settings['mail_batch_pause'] ?>">
                    </div>
                    <div class="col-12">
                        <p class="text-muted small mb-0">Sending in small batches with a pause keeps your mail server from being overloaded and reduces the chance of messages being treated as spam.</p>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-indigo px-4">Save email settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Test send -->
    <div class="col-lg-5">
        <div class="glass-card p-4 h-100">
            <h5 class="fw-bold text-dark mb-3"><i class="fa fa-paper-plane me-2 text-indigo"></i> Send a test email</h5>
            <p class="text-muted small">Check the connection works before any student or university is contacted.</p>
            <form action="<?= base_url('settings/mail/test') ?>" method="post" class="mail-test-form"
                  data-es-own-handler="1">
                <?= csrf_field() ?>
                <div class="input-group">
                    <input type="email" name="test_email" class="form-control" placeholder="your.address@example.com" required>
                    <button type="submit" class="btn btn-glass">Send test</button>
                </div>
            </form>
            <hr>
            <p class="text-muted small mb-0" id="mail_status_block">
                <?= $this->include('settings/_mail_status') ?>
            </p>
        </div>
    </div>
</div>

<!-- Templates -->
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4">
            <h5 class="fw-bold text-dark mb-1"><i class="fa fa-file-text-o me-2 text-indigo"></i> Email wording</h5>
            <p class="text-muted small mb-3">
                Edit the subject and message for each email type. Anything in
                <code>{curly braces}</code> is replaced with that recipient's real details when the mail is sent.
            </p>

            <div class="accordion" id="emailTemplates">
                <?php $i = 0; foreach ($templates as $slug => $tpl): $i++; ?>
                    <div class="accordion-item bg-transparent border-secondary border-opacity-10 mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed bg-transparent fw-semibold text-dark" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#tpl<?= $i ?>">
                                <?= esc($tpl['label']) ?>
                            </button>
                        </h2>
                        <div id="tpl<?= $i ?>" class="accordion-collapse collapse" data-bs-parent="#emailTemplates">
                            <div class="accordion-body">
                                <?php
                                    // Nothing to repaint: the subject/body inputs
                                    // already hold exactly what was submitted.
                                ?>
                                <form action="<?= base_url('settings/mail/template/' . $slug . '/store') ?>" method="post"
                                      class="js-ajax" data-title="Email wording" data-busy-button="Saving...">
                                    <?= csrf_field() ?>
                                    <div class="mb-2">
                                        <label class="form-label text-secondary small fw-semibold">Subject</label>
                                        <input type="text" name="subject" class="form-control"
                                               value="<?= esc($tpl['fields']['subject']) ?>" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label text-secondary small fw-semibold">Message</label>
                                        <textarea name="body" class="form-control" rows="9" required><?= esc($tpl['fields']['body']) ?></textarea>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        Available placeholders:
                                        <?php foreach ($tpl['tokens'] as $t): ?>
                                            <code class="me-1">{<?= esc($t) ?>}</code>
                                        <?php endforeach; ?>
                                    </p>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-indigo btn-sm">Save wording</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <?= $this->include('common/ajax_settings_mail_js') ?>
<?= $this->endSection() ?>
