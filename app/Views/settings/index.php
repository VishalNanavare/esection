<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="glass-card p-4 mb-4">
            <h3 class="fw-bold mb-1 text-dark"><i class="fa fa-cog me-2 text-indigo"></i> Settings</h3>
            <p class="text-muted small mb-0">Manage the parts of E-Section your own office should control directly, without a developer.</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php
    // Every card is listed now, even ones whose route doesn't exist until a
    // later Settings phase lands -- cheap to show, and it documents the
    // roadmap. A card for a not-yet-built phase simply 404s until it ships.
    $cards = [
        ['icon' => 'fa-university',     'title' => 'Institute Details',  'desc' => 'Name, address, contact details, logo, letterhead and signatory.', 'url' => 'settings/institute'],
        ['icon' => 'fa-calendar',       'title' => 'Academic Years',     'desc' => 'Add academic years and mark the current one.',                    'url' => 'settings/academic-years'],
        ['icon' => 'fa-graduation-cap', 'title' => 'Courses',            'desc' => 'Manage the master list of courses.',                               'url' => 'settings/courses'],
        ['icon' => 'fa-toggle-on',      'title' => 'Feature Toggles',    'desc' => 'Turn export, bulk email and other features on or off.',           'url' => 'settings/features'],
        ['icon' => 'fa-users',          'title' => 'Users',              'desc' => 'Create, edit and activate/deactivate staff accounts.',            'url' => 'settings/users'],
        ['icon' => 'fa-lock',           'title' => 'Access Rights',      'desc' => 'Choose which pages each staff account can use.',                  'url' => 'settings/access-rights'],
        ['icon' => 'fa-hashtag',        'title' => 'Document Numbering', 'desc' => 'Configure the case number prefix used on new records.',           'url' => 'settings/numbering'],
        ['icon' => 'fa-file-text-o',    'title' => 'Letter Templates',   'desc' => 'Edit the wording on every generated letter, with a live PDF preview.', 'url' => 'settings/letter-templates'],
        ['icon' => 'fa-list-alt',       'title' => 'Activity Log',       'desc' => 'See who changed a setting, and when.',                             'url' => 'settings/activity-log'],
    ];
    ?>
    <?php foreach ($cards as $card): ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= base_url($card['url']) ?>" class="text-decoration-none">
                <div class="glass-card p-4 h-100">
                    <div class="mb-2 text-indigo"><i class="fa <?= $card['icon'] ?> fa-2x"></i></div>
                    <h5 class="fw-bold text-dark mb-1"><?= esc($card['title']) ?></h5>
                    <p class="text-muted small mb-0"><?= esc($card['desc']) ?></p>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>
