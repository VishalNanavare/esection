<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Eligibility Verification Dispatch Letter (Accounts Copy)</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 14pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .subtitle {
            font-size: 10pt;
            text-align: center;
        }
        .letter-no {
            float: left;
            font-weight: bold;
        }
        .date {
            float: right;
            font-weight: bold;
        }
        .clear {
            clear: both;
        }
        .address-box {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .subject {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 15px;
            text-decoration: underline;
        }
        .content {
            text-align: justify;
            margin-bottom: 15px;
        }
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .student-table th, .student-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 10pt;
            text-align: left;
        }
        .student-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer-sig {
            margin-top: 40px;
            float: right;
            text-align: center;
            width: 250px;
        }
        .accounts-note {
            margin-top: 60px;
            border-top: 1px dashed #000;
            padding-top: 15px;
        }
    </style>
</head>
<body>

    <?php if (!empty($instituteLogoPath) && empty($instituteLetterheadPath)): ?>
        <div style="text-align: center; margin-bottom: 8px;">
            <img src="<?= esc(FCPATH . $instituteLogoPath) ?>" style="max-height: 60px;">
        </div>
    <?php endif; ?>
    <table class="header-table">
        <?php if (!empty($instituteLetterheadPath)): ?>
            <tr>
                <td style="text-align: center; padding: 0;">
                    <img src="<?= esc(FCPATH . $instituteLetterheadPath) ?>" style="width: 100%; max-width: 100%; height: auto; display: block;">
                </td>
            </tr>
        <?php else: ?>
            <tr>
                <td class="title"><?= esc($instituteUniversityTitle ?? 'UNIVERSITY OF MUMBAI') ?></td>
            </tr>
            <tr>
                <td class="subtitle"><?= esc($instituteName ?? 'INSTITUTE OF DISTANCE AND OPEN LEARNING (IDOL)') ?></td>
            </tr>
            <tr>
                <td class="subtitle"><?= nl2br(esc($instituteAddress ?? 'Dr. Shankar Dayal Sharma Bhavan, Vidyanagari, Santacruz (E), Mumbai - 400 098.')) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div>
        <span class="letter-no">Ref. No. IDOL/Eligibility/<?= esc($array_space) ?></span>
        <span class="date">Date: <?= $date ?></span>
        <div class="clear"></div>
    </div>

    <div class="address-box">
        <strong>To,</strong><br>
        <?= esc($first_row['to_name'] ?: 'The Controller of Examinations') ?>,<br>
        <?= esc_address($first_row['clg_add'] ?? '') ?>
    </div>

    <div class="subject">
        Subject: <?= $subject ?>
    </div>

    <div class="content">
        <?= $body ?>
    </div>

    <table class="student-table">
        <thead>
            <tr>
                <th style="width: 30px;">Sr.</th>
                <th>Candidate Full Name</th>
                <th>Eligibility Case No.</th>
                <th>Admission Taken In IDOL</th>
                <th>Verification Done By You</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($students as $s): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= esc(!empty($s['student_nee_name']) && $s['student_nee_name'] !== '-' ? $s['student_nee_name'] : $s['student_name']) ?></strong></td>
                    <td><?= esc($s['eligibility_case_no']) ?></td>
                    <td><?= esc($s['admission_taken_in']) ?></td>
                    <td><?= esc($s['verification_of_marksheet_done_by_you'] ?: 'Marksheet Verification') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="content">
        <?= $closing ?>
        <?php if (!empty($ddAmount)): ?>
            <br>I am also enclosing herewith a <strong>D.D.</strong> of <strong>Rs. <?= esc(number_format($ddAmount, 0)) ?> /-</strong> @ <strong>Rs. <?= esc(number_format($fees, 0)) ?> /-</strong> per document for the said purpose.
        <?php endif; ?>
    </div>

    <div class="footer-sig">
        <br><br>
        <strong>Yours faithfully,</strong><?= str_repeat('<br>', $instituteSignatureSpaceLines ?? 3) ?>
        <?php if (!empty($instituteSignatoryName)): ?>
            <strong><?= esc($instituteSignatoryName) ?></strong><br>
        <?php endif; ?>
        <strong><?= esc($instituteSignatoryDesignation ?? 'Deputy Registrar / Assistant Registrar') ?></strong><br>
        <?= esc($footerDepartment ?? 'IDOL Eligibility Section') ?>
    </div>

    <?php if (!empty($ddAmount)): ?>
        <div class="accounts-note" style="clear: both;">
            Copy forwarded to the Assistant Registrar (F &amp; A) IDOL for information. He/She is requested to issue
            <strong>D.D. of Rs. <?= esc(number_format($ddAmount, 0)) ?> ( <?= esc(ucfirst($ddAmountWords ?? '')) ?> Only. ) In Favour of <?= esc($first_row['in_favour_of'] ?? '') ?></strong>

            <div class="footer-sig">
                <br><br>
                <?php if (!empty($instituteSignatoryName)): ?>
                    <strong><?= esc($instituteSignatoryName) ?></strong><br>
                <?php endif; ?>
                <strong><?= esc($instituteSignatoryDesignation ?? 'Deputy Registrar / Assistant Registrar') ?></strong><br>
                <?= esc($footerDepartment ?? 'IDOL Eligibility Section') ?>
            </div>
        </div>
    <?php endif; ?>

</body>
</html>
