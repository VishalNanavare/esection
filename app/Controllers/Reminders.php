<?php

namespace App\Controllers;

use App\Models\StudentModel;
use App\Services\UniversityService;
use App\Services\StudentVerificationService;
use App\Services\PdfGenerationService;

class Reminders extends BaseController
{
    protected StudentModel $studentModel;
    protected UniversityService $universityService;
    protected StudentVerificationService $studentService;
    protected PdfGenerationService $pdfService;

    public function __construct()
    {
        $this->studentModel      = new StudentModel();
        $this->universityService = new UniversityService();
        $this->studentService    = new StudentVerificationService();
        $this->pdfService        = new PdfGenerationService();
    }

    public function university()
    {
        $selectedYear   = sanitize_xss($this->request->getPost('acd_year') ?? '');
        $selectedStream = sanitize_xss($this->request->getPost('stream') ?? '');
        $selectedColg   = sanitize_xss($this->request->getPost('clg_add') ?? '');

        $students = $this->studentService->searchStudentsForReminder($selectedYear, $selectedStream, $selectedColg);

        $data = [
            'title'           => 'University Marksheet Reminder Portal',
            'years'           => $this->studentModel->getDistinctYears(),
            'streams'         => $this->studentModel->getDistinctStreamsFromStudents(),
            'colleges'        => $this->universityService->getAllColleges(),
            'students'        => $students,
            'selected_year'   => $selectedYear,
            'selected_stream' => $selectedStream,
            'selected_colg'   => $selectedColg,
        ];

        return view('reminders/university', $data);
    }

    public function generateUniversityReminder()
    {
        $studentIds = $this->request->getPost('student_ids');
        $remType    = sanitize_xss($this->request->getPost('reminder_type') ?? '1st Reminder');

        $students = [];
        if (!empty($studentIds) && is_array($studentIds)) {
            $students = $this->studentModel->getStudentsByIds($studentIds);
        }

        if (empty($students)) {
            return redirect()->to(base_url('reminders/university'))->with('error', 'No student records selected for reminder.');
        }

        $data = [
            'students'      => $students,
            'first_row'     => $students[0],
            'reminder_type' => $remType,
            'date'          => date('d/m/Y')
        ];

        return $this->pdfService->renderPdfResponse('pdf/university_reminder_letter', $data, 'university_reminder_notice.pdf');
    }

    public function student()
    {
        $data = [
            'title' => 'Candidate Document Reminder Portal',
        ];
        return view('reminders/student', $data);
    }

    public function generateStudentReminder()
    {
        $data = [
            'student_name'        => sanitize_xss($this->request->getPost('student_name') ?? ''),
            'eligibility_case_no' => sanitize_xss($this->request->getPost('eligibility_case_no') ?? ''),
            'course_name'         => sanitize_xss($this->request->getPost('course_name') ?? ''),
            'missing_doc'         => sanitize_xss($this->request->getPost('missing_doc') ?? ''),
            'date'                => date('d/m/Y')
        ];

        return $this->pdfService->renderPdfResponse('pdf/student_reminder_letter', $data, 'candidate_reminder_notice.pdf');
    }
}
