<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Confirmation of Eligibility Letter</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10.5pt; line-height: 1.5; color: #000; margin: 0; padding: 20px; }
        .header-table { width: 100%; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 14pt; font-weight: bold; text-align: center; text-transform: uppercase; }
        .subtitle { font-size: 10pt; text-align: center; }
        .letter-no { float: left; font-weight: bold; }
        .date { float: right; font-weight: bold; }
        .clear { clear: both; }
        .address-box { margin-top: 15px; margin-bottom: 15px; }
        .subject { font-weight: bold; margin-top: 15px; margin-bottom: 15px; text-decoration: underline; }
        .content { text-align: justify; margin-bottom: 15px; }
        .student-table { width: 100%; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
        .student-table th, .student-table td { border: 1px solid #000; padding: 5px 6px; font-size: 8pt; text-align: left; }
        .student-table th { background-color: #f2f2f2; font-weight: bold; text-align: center; }
        .footer-sig { margin-top: 40px; float: right; text-align: center; width: 250px; }
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
        <span class="letter-no">Ref. No. IDOL/Eligibility/<?= esc($arraySpace) ?></span>
        <span class="date">Date: <?= $date ?></span>
        <div class="clear"></div>
    </div>

    <?php // This letter is always addressed to IDOL's own internal Assistant
          // Registrar office, never a target university -- matches
          // esection_basic's config/conf_ecase.php exactly, a fixed
          // recipient rather than data-driven. ?>
    <div class="address-box">
        <strong>To,</strong><br>
        <strong>Assistant Registrar,</strong><br>
        Administration/I.T. Section,<br>
        <?= esc($instituteName ?? 'Institute of Distance and Open Learning (IDOL)') ?><br>
        <?= esc($instituteUniversityTitle ?? 'University of Mumbai') ?>,<br>
        <?= nl2br(esc($instituteAddress ?? 'Vidyanagari, Santacruz (East), Mumbai - 400 098.')) ?>
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
                <th style="width: 20px;">No</th>
                <th>Name of Students</th>
                <th>Eligibility Case No.</th>
                <th>University / Institute</th>
                <th>Mig / TC</th>
                <th>Statement of Marks</th>
                <th>Passing / Degree</th>
                <th>Verified by the concerned Board/University vide letter No., Dated</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($records as $r): ?>
                <tr>
                    <td style="text-align: center;"><?= $i++ ?></td>
                    <td>
                        <strong><?= esc($r['student_name'] ?? '') ?></strong>
                        <?php if (!empty($r['student_nee_name']) && $r['student_nee_name'] !== '-'): ?>
                            <br>( Nee name : <?= esc($r['student_nee_name']) ?> )
                        <?php endif; ?>
                    </td>
                    <td><?= esc($r['case_no'] ?? '') ?></td>
                    <td><?= esc_address($r['clg_add'] ?? $r['uni_add'] ?? '') ?></td>
                    <td style="text-align: center;"><?= esc($r['mig_TC'] ?: '-') ?></td>
                    <td style="text-align: center;"><?= esc($r['s_marks'] ?: '-') ?></td>
                    <td style="text-align: center;"><?= esc($r['p_degree'] ?: '-') ?></td>
                    <td><?= esc($r['letter_no_date'] ?: '-') ?></td>
                    <td>
                        <?= esc($r['remark'] ?: '-') ?>
                        <?php if (!empty($r['conf_from'])): ?>
                            <br><?= esc($r['conf_from'] === 'other' ? $r['conf_from_text'] : $r['conf_from']) ?>
                            <?php if (!empty($r['conf_from_select'])): ?> &amp; <?= esc($r['conf_from_select']) ?><?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($r['etc_data'])): ?> &amp; <?= esc($r['etc_data']) ?><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($closing)): ?>
        <div class="content">
            <?= $closing ?>
        </div>
    <?php endif; ?>

    <div class="footer-sig">
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
