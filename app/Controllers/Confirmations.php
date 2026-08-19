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
        // trim(), NOT sanitize_xss() -- see Reminders::university(). Encoding
        // a search term before binding it corrupts the term itself; the two
        // consumers (Query Builder bind, esc()'d view) are already safe.
        $selectedYear   = trim((string) ($this->request->getGet('year') ?? ''));
        $selectedStream = trim((string) ($this->request->getGet('stream') ?? ''));

        // Both filters are optional -- blank means "no restriction", so the
        // page always shows real data on open (paginated) rather than an
        // empty "search first" prompt.
        $students = $this->studentModel->getStudentsForConfirmation($selectedYear, $selectedStream);

        $data = [
            'title'           => 'Demand Draft (DD) Payment Confirmation Portal',
            // 'metrics' removed: getStreamMetrics() runs two whole-table
            // aggregations, and confirmations/index.php never referenced
            // $metrics -- neither does its ajax_confirmations_js partial or
            // the layout. Only dashboard/index.php uses $metrics, and it gets
            // its own copy from Dashboard::index(). The service method stays;
            // this only stops calling it where the result was discarded.
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
        // trim(), NOT sanitize_xss() -- see Reminders::university(). Encoding
        // a search term before binding it corrupts the term itself; the two
        // consumers (Query Builder bind, esc()'d view) are already safe.
        $selectedYear   = trim((string) ($this->request->getGet('year') ?? ''));
        $selectedStream = trim((string) ($this->request->getGet('stream') ?? ''));

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

            return $this->respondForPending(true, $message);
        } catch (\InvalidArgumentException $e) {
            // Our own validation text -- safe and useful to show the operator.
            return $this->respondForPending(false, $e->getMessage());
        } catch (\Throwable $e) {
            // Anything else (DatabaseException, etc.) may carry SQL fragments,
            // column names or file paths. Log it; show the user a fixed string.
            log_message('error', '[Confirmations::store] {message}', ['message' => (string) $e]);

            return $this->respondForPending(
                false,
                'The confirmation could not be saved. The issue has been logged.',
                500
            );
        }
    }

    /**
     * Reply for the pending-list POST.
     *
     * Re-renders the SAME slice the operator was looking at, from the filters
     * their form carried on the query string. Note the deliberate difference
     * from the old behaviour: this used to redirect to the bare /confirmations
     * URL, which threw the filters away and returned them to page 1 of the full
     * backlog. Keeping their place is the point of the change and is recorded
     * as an accepted divergence.
     *
     * setPath() is not optional. The Pager builds its hrefs from the CURRENT
     * request URI, so on a POST to /confirmations/store?year=X&page=2 every page
     * link would come back pointing at /confirmations/store -- a POST-only route
     * that 404s the moment the operator clicks "3".
     */
    private function respondForPending(bool $ok, string $message, int $failStatus = 422)
    {
        $extra = [];

        if ($this->request->isAJAX()) {
            $selectedYear   = trim((string) ($this->request->getGet('year') ?? ''));
            $selectedStream = trim((string) ($this->request->getGet('stream') ?? ''));

            $students = $this->studentModel->getStudentsForConfirmation($selectedYear, $selectedStream);
            $pager    = $this->studentModel->pager;

            if ($pager !== null) {
                $pager->setPath('confirmations');
            }

            $extra['html'] = view('confirmations/_pending_region', [
                'students' => $students,
                'pager'    => $pager,
            ]);
        }

        return $this->respondToPost($ok, $message, base_url('confirmations'), $extra, $failStatus);
    }

    /**
     * Browse previously confirmed batches -- mirrors esection_basic's
     * conf_view.php. conf_stud_data already persists everything needed;
     * this only adds the missing browsing UI.
     */
    public function history()
    {
        // trim(), NOT sanitize_xss() -- see Reminders::university(). This
        // screen is the worst affected: both the university filter and the
        // free-text candidate-name box carry apostrophes and ampersands
        // routinely, and every one of them silently returned nothing.
        $selectedYear     = trim((string) ($this->request->getGet('year') ?? ''));
        $selectedStream   = trim((string) ($this->request->getGet('stream') ?? ''));
        $selectedColg     = trim((string) ($this->request->getGet('clg_add') ?? ''));
        $selectedName     = trim((string) ($this->request->getGet('student_name') ?? ''));
        // See ordered_date_range(): an inverted pair matches nothing, and this
        // screen has no filters partial shared with historyExport() below, so
        // the call has to appear in both readers.
        [$selectedDateFrom, $selectedDateTo] = ordered_date_range(
            trim((string) ($this->request->getGet('date_from') ?? '')),
            trim((string) ($this->request->getGet('date_to') ?? ''))
        );

        // Every filter is optional -- blank means "no restriction", so the page
        // always shows real data on open (paginated) rather than an empty
        // "search first" prompt, matching confirmations/index.php.
        $result = $this->confirmationService->getBatchSummaries(
            $selectedYear,
            $selectedStream,
            $selectedColg,
            $selectedName,
            $selectedDateFrom,
            $selectedDateTo
        );

        $data = [
            'title'           => 'Confirmation History',
            'batches'         => $result['batches'],
            'pager'           => $result['pager'],
            'selected_year'   => $selectedYear,
            'selected_stream' => $selectedStream,
            'selected_colg'   => $selectedColg,
            'selected_name'   => $selectedName,
            'selected_date_from' => $selectedDateFrom,
            'selected_date_to'   => $selectedDateTo,
        ];

        return view('confirmations/history', $data);
    }

    /** Same 4 filters as history() above, unpaginated -- exports every matching batch. */
    public function historyExport()
    {
        // trim(), NOT sanitize_xss() -- see Reminders::university(). Encoding
        // a search term before binding it corrupts the term itself; the two
        // consumers (Query Builder bind, esc()'d view) are already safe.
        $selectedYear   = trim((string) ($this->request->getGet('year') ?? ''));
        $selectedStream = trim((string) ($this->request->getGet('stream') ?? ''));
        $selectedColg   = trim((string) ($this->request->getGet('clg_add') ?? ''));
        $selectedName   = trim((string) ($this->request->getGet('student_name') ?? ''));
        [$selectedFrom, $selectedTo] = ordered_date_range(
            trim((string) ($this->request->getGet('date_from') ?? '')),
            trim((string) ($this->request->getGet('date_to') ?? ''))
        );

        $batches = $this->confirmationService->getBatchSummariesAll(
            $selectedYear,
            $selectedStream,
            $selectedColg,
            $selectedName,
            $selectedFrom,
            $selectedTo
        );

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
        $records = $this->confirmationService->getBatchDetail((int) $arraySpace);

        // Confirmation History opens this in a dialog rather than navigating,
        // so an AJAX caller gets the records on their own, from the SAME
        // partial the full page renders.
        //
        // `rows`/`count`, deliberately NOT `html`/`remaining`. Those two keys
        // belong to respondForBatch() below, and esApplyFormResult swaps any
        // reply carrying `html` into whatever data-refresh names -- so reusing
        // the name here would let this reply repaint an unrelated region.
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'rows'   => view('confirmations/_batch_rows', [
                    'records'     => $records,
                    // Both stated rather than left to the partial's defaults:
                    // Config\View::$saveData is true, so a second render in one
                    // request inherits the first one's data.
                    'highlightId' => 0,
                    'showActions' => false,
                ]),
                'count'  => count($records),
            ]);
        }

        $data = [
            'title'        => 'Confirmation Batch Detail',
            'arraySpace'   => (int) $arraySpace,
            'records'      => $records,
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
        // Read the batch before the row disappears -- afterwards there is
        // nothing left to derive it from, and the reply has to carry that
        // batch's remaining rows.
        $existing   = $this->confirmationService->getConfirmationById((int) $id);
        $arraySpace = isset($existing['array_space']) ? (int) $existing['array_space'] : null;

        try {
            $this->confirmationService->deleteConfirmation((int) $id);
        } catch (\InvalidArgumentException $e) {
            return $this->respondForBatch(false, $e->getMessage(), $arraySpace);
        }

        return $this->respondForBatch(true, 'Confirmation record deleted.', $arraySpace);
    }

    /**
     * Reply for the batch-detail delete.
     *
     * Fallback stays redirect()->back() (null) so a no-JavaScript operator
     * returns to the batch they were in, query string intact.
     *
     * `remaining` lets the client leave a batch it has just emptied:
     * batchDetail() has no emptiness guard, so the page would otherwise keep
     * rendering for a batch that no longer exists.
     */
    private function respondForBatch(bool $ok, string $message, ?int $arraySpace)
    {
        $extra = [];

        if ($this->request->isAJAX() && $arraySpace !== null) {
            $records = $this->confirmationService->getBatchDetail($arraySpace);

            $extra = [
                'html' => view('confirmations/_batch_rows', [
                    'records' => $records,
                    // Deliberately 0: the deep-link highlight belongs to the
                    // arrival from confirmations/index, not to a later refresh.
                    'highlightId' => 0,
                    // Stated for the same reason batchDetail() states it: this
                    // refresh repaints the standalone page, which keeps its
                    // Delete column, and $saveData means an unstated key can
                    // inherit a previous render's value.
                    'showActions' => true,
                ]),
                'remaining' => count($records),
            ];
        }

        return $this->respondToPost($ok, $message, null, $extra);
    }
}
