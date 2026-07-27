<?php

namespace App\Controllers;

use App\Services\StudentVerificationService;
use App\Services\PdfGenerationService;

class PdfController extends BaseController
{
    protected StudentVerificationService $studentService;
    protected PdfGenerationService $pdfService;

    public function __construct()
    {
        $this->studentService = new StudentVerificationService();
        $this->pdfService     = new PdfGenerationService();
    }

    public function dispatchLetter($arraySpace)
    {
        $decodedSpace = urldecode($arraySpace);
        $students     = $this->studentService->getStudentsByArraySpace($decodedSpace);

        if (empty($students)) {
            return 'No verification dispatch records found for batch code: ' . esc($decodedSpace);
        }

        $firstRow = $students[0];

        $data = [
            'first_row'     => $firstRow,
            'students'      => $students,
            'array_space'   => $decodedSpace,
            'dispatch_date' => date('d/m/Y')
        ];

        return $this->pdfService->renderPdfResponse('pdf/dispatch_letter', $data, 'verification_dispatch_' . $decodedSpace . '.pdf');
    }
}
