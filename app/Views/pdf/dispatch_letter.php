<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Eligibility Verification Dispatch Letter</title>
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
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td class="title">UNIVERSITY OF MUMBAI</td>
        </tr>
        <tr>
            <td class="subtitle">INSTITUTE OF DISTANCE AND OPEN LEARNING (IDOL)</td>
        </tr>
        <tr>
            <td class="subtitle">Dr. Shankar Dayal Sharma Bhavan, Vidyanagari, Santacruz (E), Mumbai - 400 098.</td>
        </tr>
    </table>

    <div>
        <span class="letter-no">Ref. No. IDOL/Eligibility/<?= esc($array_space) ?></span>
        <span class="date">Date: <?= $date ?></span>
        <div class="clear"></div>
    </div>

    <div class="address-box">
        <strong>To,</strong><br>
        <?= esc($first_row['to_name'] ?: 'The Controller of Examinations') ?>,<br>
        <?= nl2br(esc($first_row['clg_add'])) ?>
    </div>

    <div class="subject">
        Subject: Verification of Marksheet / Passing Certificate / Migration Certificate.
    </div>

    <div class="content">
        Sir/Madam,<br>
        I am to forward herewith the copies of Marksheet / Passing / Migration Certificates of the undermentioned candidate(s) who have been admitted to <strong><?= esc($first_row['admission_taken_in']) ?></strong> course in this Institute during the academic year <strong><?= esc($first_row['admission_taken_year']) ?></strong> for verification.
    </div>

    <div class="content">
        Kindly verify the authenticity of the attached document(s) from your office records and return the same duly verified at an early date.
        <?php if (!empty($first_row['in_favour_of'])): ?>
            <br>Fee details/payment favouring: <strong><?= esc($first_row['in_favour_of']) ?></strong>.
        <?php endif; ?>
    </div>

    <table class="student-table">
        <thead>
            <tr>
                <th style="width: 30px;">Sr.</th>
                <th>Candidate Full Name</th>
                <th>Nee / Maiden Name</th>
                <th>Eligibility Case No.</th>
                <th>Verification Done By You</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($students as $s): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td><strong><?= esc($s['student_name']) ?></strong></td>
                    <td><?= esc($s['student_nee_name'] ?: '-') ?></td>
                    <td><?= esc($s['eligibility_case_no']) ?></td>
                    <td><?= esc($s['verification_of_marksheet_done_by_you'] ?: 'Marksheet Verification') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="content">
        Thanking you,
    </div>

    <div class="footer-sig">
        <br><br>
        <strong>Yours faithfully,</strong><br><br><br>
        <strong>Deputy Registrar / Assistant Registrar</strong><br>
        IDOL Eligibility Section
    </div>

</body>
</html>
