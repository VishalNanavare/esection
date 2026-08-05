<?php

namespace App\Controllers;

use App\Models\StudentModel;
use App\Services\ConfirmationService;
use App\Services\ExcelExportService;
use App\Services\PdfGenerationService;
use App\Services\LetterTemplateService;

class Confirmations extends BaseController
{
    protected ConfirmationService $confirmationService;
    protected StudentModel $studentModel;
    protected PdfGenerationService $pdfService;
    protected LetterTemplateService $letterTemplateService;

    public function __construct()
    {
        $this->confirmationService = new ConfirmationService();
        $this->studentModel      = new StudentModel();
        $this->pdfService             = new PdfGenerationService();
        $this->letterTemplateService = new LetterTemplateService();
    }

    public function index()
    {
        $selectedYear   = sanitize_xss($this->request->getGet('year') ?? '');
        $selectedStream = sanitize_xss($this->request->getGet('stream') ?? '');

        // Both filters are optional -- blank means "no restriction", so the
        // page always shows real data on open (paginated) rather than an
        // empty "search first" prompt.
        $students = $this->studentModel->getStudentsForConfirmation($selectedYear, $selectedStream);

        $data = [
            'title'           => 'Demand Draft (DD) Payment Confirmation Portal',
            'metrics'         => $this->confirmationService->getStreamMetrics(),
            'students'        => $students,
            'pager'           => $this->studentModel->pager,
            'selected_year'   => $selectedYear,
            'selected_stream' => $selectedStream
        ];

        return view('confirmations/index', $data);
    }

    /** Same filters as index() above, unpaginated -- exports every matching row, not just page 1. */
    public function export()
    {
        $selectedYear   = sanitize_xss($this->request->getGet('year') ?? '');
        $selectedStream = sanitize_xss($this->request->getGet('stream') ?? '');

        $students = $this->studentModel->getStudentsForConfirmationAll($selectedYear, $selectedStream);

        $columns = [
            ['header' => 'Candidate'],
            ['header' => 'Nee Name'],
            ['header' => 'Case No.'],
            ['header' => 'Target University', 'width' => 35],
            ['header' => 'Academic Year'],
            ['header' => 'Stream'],
            ['header' => 'Status'],
        ];

        $rows = array_map(static function (array $s): array {
            return [
                $s['student_name'],
                $s['student_nee_name'] ?: '-',
                $s['eligibility_case_no'],
                $s['clg_add'],
                $s['admission_taken_year'],
                $s['admission_taken_in'],
                !empty($s['confirmation_id']) ? 'Confirmed' : 'Pending',
            ];
        }, $students);

        $filterParts = [];
        if ($selectedYear !== '')   { $filterParts[] = 'Year=' . $selectedYear; }
        if ($selectedStream !== '') { $filterParts[] = 'Stream=' . $selectedStream; }
        $summaryLine = 'Filters: ' . ($filterParts === [] ? 'none' : implode(', ', $filterParts))
            . ' | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        $filenameBase = 'confirmations'
            . ($selectedYear !== '' ? '_year-' . preg_replace('/[^A-Za-z0-9-]/', '', $selectedYear) : '')
            . ($selectedStream !== '' ? '_stream-' . preg_replace('/[^A-Za-z0-9-]/', '', $selectedStream) : '');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, $filenameBase, $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('confirmations'))->with('error', $e->getMessage());
        }
    }

    public function store()
    {
        $username = (string) (session()->get('username') ?? '');

        try {
            $res = $this->confirmationService->storeConfirmation($this->request->getPost(), $username);

            $message = "Eligibility confirmed for {$res['count']} candidate(s).";
            if (! empty($res['skipped'])) {
                $message .= " {$res['skipped']} already had a confirmation and were skipped.";
            }

            return redirect()->to(base_url('confirmations'))->with('success', $message);
        } catch (\InvalidArgumentException $e) {
            // Our own validation text -- safe and useful to show the operator.
            return redirect()->to(base_url('confirmations'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Anything else (DatabaseException, etc.) may carry SQL fragments,
            // column names or file paths. Log it; show the user a fixed string.
            log_message('error', '[Confirmations::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('confirmations'))
                ->with('error', 'The confirmation could not be saved. The issue has been logged.');
        }
    }

    /**
     * Browse previously confirmed batches -- mirrors esection_basic's
     * conf_view.php. conf_stud_data already persists everything needed;
     * this only adds the missing browsing UI.
     */
    public function history()
    {
        $selectedYear     = sanitize_xss($this->request->getGet('year') ?? '');
        $selectedStream   = sanitize_xss($this->request->getGet('stream') ?? '');
        $selectedColg     = sanitize_xss($this->request->getGet('clg_add') ?? '');
        $selectedName     = sanitize_xss($this->request->getGet('student_name') ?? '');

        // All four filters are optional -- blank means "no restriction", so
        // the page always shows real data on open (paginated) rather than an
        // empty "search first" prompt, matching confirmations/index.php.
        $result = $this->confirmationService->getBatchSummaries($selectedYear, $selectedStream, $selectedColg, $selectedName);

        $data = [
            'title'           => 'Confirmation History',
            'batches'         => $result['batches'],
            'pager'           => $result['pager'],
            'selected_year'   => $selectedYear,
            'selected_stream' => $selectedStream,
            'selected_colg'   => $selectedColg,
            'selected_name'   => $selectedName,
        ];

        return view('confirmations/history', $data);
    }

    /** Same 4 filters as history() above, unpaginated -- exports every matching batch. */
    public function historyExport()
    {
        $selectedYear   = sanitize_xss($this->request->getGet('year') ?? '');
        $selectedStream = sanitize_xss($this->request->getGet('stream') ?? '');
        $selectedColg   = sanitize_xss($this->request->getGet('clg_add') ?? '');
        $selectedName   = sanitize_xss($this->request->getGet('student_name') ?? '');

        $batches = $this->confirmationService->getBatchSummariesAll($selectedYear, $selectedStream, $selectedColg, $selectedName);

        $columns = [
            ['header' => 'Batch'],
            ['header' => 'Confirmed On', 'format' => 'dd/mm/yyyy hh:mm'],
            ['header' => 'Confirmed By'],
            ['header' => 'Candidates', 'format' => '#,##0'],
        ];

        $rows = array_map(static function (array $b): array {
            $confirmedOn = is_numeric($b['en_time'])
                ? (new \DateTimeImmutable())->setTimestamp((int) $b['en_time'])
                : $b['en_time'];

            return [
                (int) $b['array_space'],
                $confirmedOn,
                $b['en_by'] ?: '-',
                (int) $b['student_count'],
            ];
        }, $batches);

        $filterParts = [];
        if ($selectedYear !== '')   { $filterParts[] = 'Year=' . $selectedYear; }
        if ($selectedStream !== '') { $filterParts[] = 'Stream=' . $selectedStream; }
        if ($selectedColg !== '')   { $filterParts[] = 'University=' . $selectedColg; }
        if ($selectedName !== '')   { $filterParts[] = 'Student=' . $selectedName; }
        $summaryLine = 'Filters: ' . ($filterParts === [] ? 'none' : implode(', ', $filterParts))
            . ' | ' . count($rows) . ' row(s) | Generated ' . date('d/m/Y H:i');

        $filenameBase = 'confirmations_history'
            . ($selectedYear !== '' ? '_year-' . preg_replace('/[^A-Za-z0-9-]/', '', $selectedYear) : '')
            . ($selectedStream !== '' ? '_stream-' . preg_replace('/[^A-Za-z0-9-]/', '', $selectedStream) : '');

        try {
            return (new ExcelExportService())->exportToXlsx($columns, $rows, $filenameBase, $summaryLine);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('confirmations/history'))->with('error', $e->getMessage());
        }
    }

    /** One batch's full student detail, for the expand/drill-down view. */
    public function batchDetail($arraySpace)
    {
        $data = [
            'title'        => 'Confirmation Batch Detail',
            'arraySpace'   => (int) $arraySpace,
            'records'      => $this->confirmationService->getBatchDetail((int) $arraySpace),
            // Optional ?highlight=<conf_stud_data.id>, set by the "Already
            // confirmed" deep link on confirmations/index.php so the specific
            // student that was clicked can be scrolled to and highlighted.
            'highlightId'  => (int) ($this->request->getGet('highlight') ?? 0),
        ];

        return view('confirmations/batch_detail', $data);
    }

    /**
     * "Confirmation of Eligibility" letter -- mirrors esection_basic's
     * config/conf_ecase.php, a genuinely distinct letter type from the
     * dispatch letter (addressed to IDOL's own internal Assistant
     * Registrar office, not a target university), confirming the batch of
     * students that already went through the eligibility checklist.
     */
    public function eligibilityPdf($arraySpace)
    {
        $records = $this->confirmationService->getBatchDetail((int) $arraySpace);
        if (empty($records)) {
            return redirect()->to(base_url('confirmations/history'))->with('error', 'This batch has no records.');
        }

        $first = $records[0];
        $count = count($records);

        $rendered = $this->letterTemplateService->render(
            'confirmation_eligibility',
            $this->letterTemplateService->getFields('confirmation_eligibility'),
            [
                'academic_year'        => $first['acd_year'] ?? '',
                'course'               => $first['stream'] ?? '',
                'student_count_phrase' => '( ' . $count . ' ) ' . ($count === 1 ? 'student' : 'students'),
            ]
        );

        $data = array_merge($rendered, [
            'arraySpace' => (int) $arraySpace,
            'records'    => $records,
            'date'       => date('d/m/Y'),
        ]);

        return $this->pdfService->renderPdfResponse('pdf/confirmation_eligibility_letter', $data, 'confirmation_of_eligibility_' . $arraySpace . '.pdf');
    }

    /** Mirrors esection_basic's config/stud-delete.php `?r=` branch. */
    public function delete($id)
    {
        try {
            $this->confirmationService->deleteConfirmation((int) $id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Confirmation record deleted.');
    }
}
