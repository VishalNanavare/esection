<?php

namespace App\Controllers;

use App\Services\StudentVerificationService;
use App\Services\PdfGenerationService;
use App\Services\LetterTemplateService;
use App\Services\UniversityService;

class PdfController extends BaseController
{
    protected StudentVerificationService $studentService;
    protected PdfGenerationService $pdfService;
    protected LetterTemplateService $letterTemplateService;
    protected UniversityService $universityService;

    public function __construct()
    {
        $this->studentService        = new StudentVerificationService();
        $this->pdfService             = new PdfGenerationService();
        $this->letterTemplateService = new LetterTemplateService();
        $this->universityService     = new UniversityService();
    }

    public function dispatchLetter($arraySpace)
    {
        $decodedSpace = urldecode($arraySpace);
        $students     = $this->studentService->getStudentsByArraySpace($decodedSpace);

        if (empty($students)) {
            // Same shape as every other PDF action in this app
            // (Confirmations::eligibilityPdf, Regularization::pdf, the four in
            // Reminders): flash and send the operator back to a real screen.
            // Returning a bare string made CodeIgniter emit it as the entire
            // response -- a dead-end page with no navigation.
            return redirect()
                ->to(base_url('students/history'))
                ->with('error', 'No verification dispatch records found for batch code: ' . $decodedSpace);
        }

        $firstRow = $students[0];

        $rendered = $this->letterTemplateService->render(
            'dispatch',
            $this->letterTemplateService->getFields('dispatch'),
            [
                'course'        => $firstRow['admission_taken_in'] ?? '',
                'academic_year' => $firstRow['admission_taken_year'] ?? '',
            ]
        );

        $data = array_merge($rendered, [
            'first_row'     => $firstRow,
            'students'      => $students,
            'array_space'   => $decodedSpace,
            // Key must be 'date': that is what every pdf/*.php template reads.
            'date'          => date('d/m/Y'),
            'dispatch_date' => date('d/m/Y'),
        ]);

        return $this->pdfService->renderPdfResponse('pdf/dispatch_letter', $data, 'verification_dispatch_' . $decodedSpace . '.pdf');
    }

    /**
     * "Accounts copy" variant of the dispatch letter -- mirrors
     * esection_basic's config/ac_view_pdf.php. Spells out the DD amount
     * (fees x candidate count) in words and adds a second "copy forwarded
     * to Accounts" section, only when a fee is actually on record for the
     * target university.
     *
     * student_details.clg_add stores the university's postal Address as
     * free text, not a foreign key, so the fee has to be found via a
     * best-effort reverse address lookup -- if it doesn't match any
     * college_details row (e.g. the address was hand-edited after entry),
     * this degrades to the same "no fee, no DD paragraph" fallback legacy
     * itself used whenever fees was 0.
     */
    public function dispatchAccountsLetter($arraySpace)
    {
        $decodedSpace = urldecode($arraySpace);
        $students     = $this->studentService->getStudentsByArraySpace($decodedSpace);

        if (empty($students)) {
            return redirect()
                ->to(base_url('students/history'))
                ->with('error', 'No verification dispatch records found for batch code: ' . $decodedSpace);
        }

        $firstRow = $students[0];

        $college = $this->universityService->getCollegeByAddress((string) ($firstRow['clg_add'] ?? ''));
        $fees    = (float) ($college['fees'] ?? 0);
        $ddAmount = $fees > 0 ? $fees * count($students) : 0;

        $firstRow['fees'] = $fees;
        if (empty($firstRow['in_favour_of']) && $college) {
            $firstRow['in_favour_of'] = $college['in_favour_of'] ?? '';
        }

        $rendered = $this->letterTemplateService->render(
            'dispatch_accounts',
            $this->letterTemplateService->getFields('dispatch_accounts'),
            [
                'academic_year' => $firstRow['admission_taken_year'] ?? '',
            ]
        );

        $data = array_merge($rendered, [
            'first_row'     => $firstRow,
            'students'      => $students,
            'array_space'   => $decodedSpace,
            'fees'          => $fees,
            'ddAmount'      => $ddAmount,
            'ddAmountWords' => $ddAmount > 0 ? number_to_words_indian($ddAmount) : '',
            'date'          => date('d/m/Y'),
        ]);

        return $this->pdfService->renderPdfResponse('pdf/dispatch_accounts_letter', $data, 'verification_dispatch_accounts_' . $decodedSpace . '.pdf');
    }
}
