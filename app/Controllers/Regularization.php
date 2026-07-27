<?php

namespace App\Controllers;

use App\Models\StreamModel;
use App\Services\UniversityService;
use App\Services\PdfGenerationService;

class Regularization extends BaseController
{
    protected StreamModel $streamModel;
    protected UniversityService $universityService;
    protected PdfGenerationService $pdfService;

    public function __construct()
    {
        $this->streamModel       = new StreamModel();
        $this->universityService = new UniversityService();
        $this->pdfService        = new PdfGenerationService();
    }

    public function index()
    {
        $data = [
            'title'    => 'Student Eligibility Regularization Portal',
            'streams'  => $this->streamModel->getAllStreams(),
            'colleges' => $this->universityService->getAllColleges(),
        ];

        return view('regularization/index', $data);
    }

    public function generateLetter()
    {
        $data = [
            'admission_letter_for'  => sanitize_xss($this->request->getPost('admission_letter_for') ?? ''),
            'admission_letter_date' => sanitize_xss($this->request->getPost('admission_letter_date') ?? ''),
            'passing_course'        => sanitize_xss($this->request->getPost('admission_passing_course') ?? ''),
            'university_name'       => sanitize_xss($this->request->getPost('clg_add') ?? ''),
            'student_name'          => sanitize_xss($this->request->getPost('student_name') ?? ''),
            'eligibility_case_no'   => sanitize_xss($this->request->getPost('eligibility_case_no') ?? ''),
            'date'                  => date('d/m/Y')
        ];

        return $this->pdfService->renderPdfResponse('pdf/regularization_letter', $data, 'regularization_letter.pdf');
    }
}
