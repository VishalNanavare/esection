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
    <div class="header">
        <div class="title">UNIVERSITY OF MUMBAI</div>
        <div>INSTITUTE OF DISTANCE AND OPEN LEARNING (IDOL)</div>
        <div>Vidyanagari, Santacruz (E), Mumbai - 400 098.</div>
    </div>

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
        Subject: Submission of Pending Original Documents for Eligibility Verification.
    </div>

    <div>
        Dear Candidate,<br>
        You are hereby informed that your eligibility verification for <strong><?= esc($course_name) ?></strong> course is pending due to non-submission of the following document(s):
        <br><br>
        <strong>Missing Documents:</strong> <?= esc($missing_doc) ?>
    </div>

    <div style="margin-top: 15px;">
        Please submit the required original documents to the IDOL Eligibility Section within 15 days, failing which your admission eligibility may be cancelled.
    </div>

    <div class="footer">
        <br><br>
        <strong>Deputy Registrar / Assistant Registrar</strong><br>
        IDOL Eligibility Section
    </div>
</body>
</html>
