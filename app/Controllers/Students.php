<?php

namespace App\Controllers;

use App\Services\DocumentNumberingService;
use App\Services\ExcelExportService;
use App\Services\StudentVerificationService;
use App\Services\UniversityService;

class Students extends BaseController
{
    protected StudentVerificationService $studentService;
    protected UniversityService $universityService;
    protected DocumentNumberingService $documentNumberingService;

    public function __construct()
    {
        $this->studentService           = new StudentVerificationService();
        $this->universityService        = new UniversityService();
        $this->documentNumberingService = new DocumentNumberingService();
    }

    public function newForm()
    {
        $data = [
            'title'          => 'New Student Verification Form',
            'common_no'      => $this->studentService->getNextCommonNo(),
            // 'colleges' removed: this materialised all 469 college_details
            // rows (two varchar(1500) columns) on every page open, and
            // students/new_form.php never referenced $colleges. The university
            // picker here is a Select2 fed by GET /api/colleges. getAllColleges()
            // itself stays -- Universities::index() still uses it.
            'suggestedCaseNo' => $this->documentNumberingService->previewNext(),
        ];

        return view('students/new_form', $data);
    }

    /**
     * A fresh suggested case number, in the configured Settings > Document
     * Numbering format -- called after each candidate is added, so the next
     * row starts pre-filled instead of every candidate getting today's first
     * suggestion. Always editable; this only changes the starting value.
     */
    public function generateCaseNo()
    {
        return $this->response->setJSON(['case_no' => $this->documentNumberingService->previewNext()]);
    }

    public function getCollegeInfo($id)
    {
        $college = $this->universityService->getCollegeById((int)$id);
        if ($college) {
            return $this->response->setJSON([
                'status'       => 'success',
                'address'      => $college['Address'],
                'in_favour_of' => $college['in_favour_of'],
                'fees'         => $college['fees'],
                'head_name'    => $college['head_name']
            ]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'University not found']);
    }

    public function storeBatch()
    {
        $json     = $this->request->getJSON(true);
        $username = session()->get('username') ?? 'esection1';

        try {
            $res = $this->studentService->storeCandidateBatch($json ?: [], $username);
            return $this->response->setJSON([
                'status'       => 'success',
                'message'      => "{$res['count']} student verification cases saved successfully.",
                'array_space'  => $res['array_space'],
                'redirect_url' => base_url('pdf/dispatch/' . urlencode($res['array_space']))
            ]);
        } catch (\Exception $e) {
            return $this->response->setJSON(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /** Browse previously submitted batches -- mirrors esection_basic's view.php. */
    public function history()
    {
        $data = [
            'title'   => 'Verification Batch History',
            'batches' => $this->studentService->getBatchSummaries(),
        ];

        return view('students/history', $data);
    }

    /** No server-side filters on this page -- every batch, always. */
    public function historyExport()
    {
        $batches = $this->studentService->getBatchSummaries();

        $columns = [
            ['header' => 'Batch'],
            ['header' => 'University Address', 'width' => 35],
            ['header' => 'Admission Taken In'],
            ['header' => 'Academic Year'],
            ['header' => 'Candidates', 'format' => '#,##0'],
            ['header' => 'Created', 'format' => 'dd/mm/yyyy hh:mm'],
        ];

        $rows = array_map(static function (array $b): array {
            $created = is_numeric($b['en_time'])
                ? (new \DateTimeImmutable())->setTimestamp((int) $b['en_time'])
                : $b['en_time'];

            return [
                $b['array_space'],
                $b['clg_add'],
                $b['admission_taken_in'],
                $b['admission_taken_year'],
                (int) $b['student_count'],
                $created,
            ];
        }, $batches);

        $summaryLine = 'Filters: none | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, 'students_history', $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('students/history'))->with('error', $e->getMessage());
        }
    }

    /** One batch's full student list, for the browse/edit/delete view. */
    public function batchDetail($arraySpace)
    {
        $data = [
            'title'      => 'Verification Batch Detail',
            'arraySpace' => $arraySpace,
            'students'   => $this->studentService->getStudentsByArraySpace($arraySpace),
        ];

        return view('students/batch_detail', $data);
    }

    public function getJson($id)
    {
        $student = $this->studentService->getStudentById((int) $id);
        if ($student) {
            return $this->response->setJSON(['status' => 'success', 'data' => $student]);
        }

        return $this->response->setJSON(['status' => 'error', 'message' => 'Student not found']);
    }

    /** Mirrors esection_basic's update_new_form.php -> config/stud_update_data.php (only 4 fields are editable there too). */
    public function update($id)
    {
        try {
            $this->studentService->updateStudent((int) $id, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Candidate updated.');
    }

    /** Hard delete -- mirrors esection_basic's config/stud-delete.php `?q=` branch. */
    public function delete($id)
    {
        try {
            $this->studentService->deleteStudent((int) $id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Candidate deleted.');
    }
}
