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
    <div class="header">
        <div class="title">UNIVERSITY OF MUMBAI</div>
        <div>INSTITUTE OF DISTANCE AND OPEN LEARNING (IDOL)</div>
        <div>Vidyanagari, Santacruz (E), Mumbai - 400 098.</div>
    </div>

    <div>
        <strong>Ref No.: IDOL/REM/<?= date('Y') ?>/<?= rand(1000,9999) ?></strong>
        <span style="float: right;">Date: <?= $date ?></span>
    </div>

    <div style="margin-top: 15px;">
        To,<br>
        <strong><?= esc($first_row['to_name'] ?: 'The Controller of Examinations') ?></strong>,<br>
        <?= nl2br(esc($first_row['clg_add'])) ?>
    </div>

    <div class="subject">
        Subject: <?= esc($reminder_type) ?> - Verification of Marksheet / Passing Certificate.
    </div>

    <div>
        Sir/Madam,<br>
        This is a <strong><?= esc($reminder_type) ?></strong> regarding the verification of marksheet/certificates of candidate(s) admitted to <strong><?= esc($first_row['admission_taken_in']) ?></strong> during academic year <strong><?= esc($first_row['admission_taken_year']) ?></strong>.
    </div>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 30px;">#</th>
                <th>Candidate Full Name</th>
                <th>Eligibility Case No.</th>
                <th>Verification Requested</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; foreach ($students as $s): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= esc($s['student_name']) ?></strong></td>
                    <td><?= esc($s['eligibility_case_no']) ?></td>
                    <td><?= esc($s['verification_of_marksheet_done_by_you'] ?: 'Marksheet Verification') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div>
        Kindly verify and return the confirmed verification report at your earliest convenience.
    </div>

    <div class="footer">
        <br><br>
        <strong>Deputy Registrar / Assistant Registrar</strong><br>
        IDOL Eligibility Section
    </div>
</body>
</html>
