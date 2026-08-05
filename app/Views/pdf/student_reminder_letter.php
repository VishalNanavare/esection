<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Candidate Document Reminder Letter</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11pt; line-height: 1.5; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 14pt; font-weight: bold; }
        .subject { font-weight: bold; text-decoration: underline; margin: 15px 0; }
        .footer { margin-top: 50px; float: right; text-align: center; width: 250px; }
    </style>
</head>
<body>
    <?php if (!empty($instituteLogoPath) && empty($instituteLetterheadPath)): ?>
        <div style="text-align: center; margin-bottom: 8px;">
            <img src="<?= esc(FCPATH . $instituteLogoPath) ?>" style="max-height: 60px;">
        </div>
    <?php endif; ?>
    <?php if (!empty($instituteLetterheadPath)): ?>
        <div class="header" style="padding: 0;">
            <img src="<?= esc(FCPATH . $instituteLetterheadPath) ?>" style="width: 100%; max-width: 100%; height: auto; display: block;">
        </div>
    <?php else: ?>
        <div class="header">
            <div class="title"><?= esc($instituteUniversityTitle ?? 'UNIVERSITY OF MUMBAI') ?></div>
            <div><?= esc($instituteName ?? 'INSTITUTE OF DISTANCE AND OPEN LEARNING (IDOL)') ?></div>
            <div><?= nl2br(esc($instituteAddress ?? 'Vidyanagari, Santacruz (E), Mumbai - 400 098.')) ?></div>
        </div>
    <?php endif; ?>

    <div>
        <strong>Ref No.: IDOL/STUD-REM/<?= date('Y') ?>/<?= rand(100,999) ?></strong>
        <span style="float: right;">Date: <?= $date ?></span>
    </div>

    <div style="margin-top: 15px;">
        To,<br>
        <strong><?= esc($student_name) ?></strong>,<br>
        Admitted Course: <strong><?= esc($course_name) ?></strong><br>
        Case No.: <strong><?= esc($eligibility_case_no) ?></strong>
    </div>

    <div class="subject">
        Subject: <?= $subject ?>
    </div>

    <div>
        <?= $body ?>
    </div>

    <div style="margin-top: 15px;">
        <?= $closing ?>
    </div>

    <div class="footer">
        <?= str_repeat('<br>', $instituteSignatureSpaceLines ?? 2) ?>
        <?php if (!empty($instituteSignatoryName)): ?>
            <strong><?= esc($instituteSignatoryName) ?></strong><br>
        <?php endif; ?>
        <strong><?= esc($instituteSignatoryDesignation ?? 'Deputy Registrar / Assistant Registrar') ?></strong><br>
        <?= esc($footerDepartment ?? 'IDOL Eligibility Section') ?>
    </div>
</body>
</html>
