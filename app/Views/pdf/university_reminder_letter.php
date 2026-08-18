<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>University Verification Reminder Letter</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11pt; line-height: 1.5; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 14pt; font-weight: bold; }
        .subject { font-weight: bold; text-decoration: underline; margin: 15px 0; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .table th, .table td { border: 1px solid #000; padding: 6px 8px; font-size: 10pt; text-align: left; }
        .table th { background: #f2f2f2; }
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
        <strong>Ref No.: IDOL/REM/<?= date('Y') ?>/<?= rand(1000,9999) ?></strong>
        <span style="float: right;">Date: <?= $date ?></span>
    </div>

    <div style="margin-top: 15px;">
        To,<br>
        <strong><?= esc($first_row['to_name'] ?: 'The Controller of Examinations') ?></strong>,<br>
        <?= esc_address($first_row['clg_add'] ?? '') ?>
    </div>

    <div class="subject">
        Subject: <?= $subject ?>
    </div>

    <div>
        <?= $body ?>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Candidate Full Name</th>
                <th>Eligibility Case No.</th>
                <th>Reminder History</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; foreach ($students as $s): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= esc($s['student_name']) ?></strong></td>
                    <td><?= esc($s['eligibility_case_no']) ?></td>
                    <td>
                        <?php if (!empty($s['notes'])): ?>
                            <?php foreach ($s['notes'] as $ni => $note): ?>
                                <?php if ($ni > 0): ?><hr style="margin: 4px 0;"><?php endif; ?>
                                <?= esc($note['note_text']) ?><br>Date: <?= esc(!empty($note['note_date']) ? date('d/m/Y', strtotime($note['note_date'])) : '') ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?= esc($s['verification_of_marksheet_done_by_you'] ?? 'Marksheet Verification') ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div>
        <?= $closing ?>
    </div>

    <div class="footer">
        <br><br>
        <strong>Yours faithfully,</strong><?= str_repeat('<br>', $instituteSignatureSpaceLines ?? 3) ?>
        <?php if (!empty($instituteSignatoryName)): ?>
            <strong><?= esc($instituteSignatoryName) ?></strong><br>
        <?php endif; ?>
        <strong><?= esc($instituteSignatoryDesignation ?? 'Deputy Registrar / Assistant Registrar') ?></strong><br>
        <?= esc($footerDepartment ?? 'IDOL Eligibility Section') ?>
    </div>
</body>
</html>
