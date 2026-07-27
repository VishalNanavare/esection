<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Student Eligibility Regularization Letter</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11pt; line-height: 1.5; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 14pt; font-weight: bold; }
        .subject { font-weight: bold; text-decoration: underline; margin: 15px 0; }
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
        <strong>Ref No.: IDOL/REG/<?= date('Y') ?>/<?= rand(100,999) ?></strong>
        <span style="float: right;">Date: <?= $date ?></span>
    </div>

    <div style="margin-top: 15px;">
        To,<br>
        <strong><?= esc($admission_letter_for) ?></strong>,<br>
        <?= esc($university_name) ?>
    </div>

    <div class="subject">
        Subject: Eligibility Regularization of Candidate <?= esc($student_name) ?>.
    </div>

    <div>
        Sir/Madam,<br>
        With reference to the eligibility verification for <strong><?= esc($student_name) ?></strong> (Eligibility Case No: <strong><?= esc($eligibility_case_no) ?></strong>) admitted to <strong><?= esc($passing_course) ?></strong> program, the submitted documents have been reviewed and regularized by this Institute.
    </div>

    <div style="margin-top: 15px;">
        Kindly record the eligibility regularization status in your records.
    </div>

    <div class="footer">
        <br><br>
        <strong>Deputy Registrar / Assistant Registrar</strong><br>
        IDOL Eligibility Section
    </div>
</body>
</html>
