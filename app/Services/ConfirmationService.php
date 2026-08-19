<?php

namespace App\Services;

use App\Models\ConfirmationModel;
use App\Models\StudentModel;
use App\Models\StreamModel;
use App\Models\CourseModel;
use App\Services\StudentVerificationService;

class ConfirmationService
{
    /** Mirrors esection_basic's con_view.php dropdown options exactly. */
    public const CONF_FROM_OPTIONS = ['Ranade Bhavan', 'Dr Babasaheb Ambedkar Bhavan', 'other'];
    public const CLARIFICATION_OPTIONS = ['old', 'TC'];
    public const NAME_CHANGE_OPTIONS = ['Gazette', 'Marriage Certificate'];

    protected ConfirmationModel $confirmationModel;
    protected StudentModel $studentModel;
    protected StreamModel $streamModel;
    protected CourseModel $courseModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->confirmationModel  = new ConfirmationModel();
        $this->studentModel       = new StudentModel();
        $this->streamModel        = new StreamModel();
        $this->courseModel        = new CourseModel();
        $this->activityLogService = new ActivityLogService();
    }

    public function getStreamMetrics(): array
    {
        $streams         = $this->streamModel->getAllStreams();
        $totalCounts     = $this->studentModel->getGroupedStudentCounts();
        $confirmedCounts = $this->studentModel->getGroupedConfirmedCounts();

        $metrics = [];
        foreach ($streams as $s) {
            $streamName = $s['Division'] ?? $s['full_name'] ?? '';
            if (empty($streamName)) continue;

            $total     = 0;
            $confirmed = 0;

            // Match exact or fuzzy stream name
            foreach ($totalCounts as $stName => $cnt) {
                if (stripos($stName, $streamName) !== false || stripos($streamName, $stName) !== false) {
                    $total += $cnt;
                }
            }

            foreach ($confirmedCounts as $stName => $cnt) {
                if (stripos($stName, $streamName) !== false || stripos($streamName, $stName) !== false) {
                    $confirmed += $cnt;
                }
            }

            $pending = max(0, $total - $confirmed);

            $metrics[] = [
                'stream'    => $streamName,
                'total'     => $total,
                'confirmed' => $confirmed,
                'pending'   => $pending
            ];
        }

        return $metrics;
    }

    /**
     * Now backed by the new courses table rather than the legacy
     * stream_details -- Settings Phase 10. Method name/signature kept
     * unchanged so Api::streams() (and all 4 pages that share that one
     * endpoint) needed zero changes. stream_details/StreamModel are left
     * untouched; getStreamMetrics() above still reads from them.
     */
    public function searchStreamsForSelect2(?string $term): array
    {
        return $this->courseModel->searchForSelect2($term);
    }

    /**
     * @param array $postData Expects `student_ids[]` plus a nested
     *              `checklist[student_id][field]` array per selected
     *              student (mig_tc, p_degree, s_marks, letter_no_date,
     *              remark, conf_from, conf_from_text, conf_from_select,
     *              etc_data) -- mirrors con_view.php's per-student form,
     *              just keyed by student id instead of a positional index.
     */
    public function storeConfirmation(array $postData, string $username): array
    {
        $studentIds = $postData['student_ids'] ?? [];

        if (empty($studentIds) || ! is_array($studentIds)) {
            throw new \InvalidArgumentException('No student records selected for confirmation.');
        }

        // Same ceiling the dispatch side already enforces. The screen submits
        // whatever is ticked on one page, so this is far above real use -- it
        // stops a hand-made POST confirming every pending candidate in the
        // system in a single request, which is not an action any screen
        // offers and not one that could be undone from the UI.
        if (count($studentIds) > StudentVerificationService::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException(
                'One confirmation batch cannot hold more than '
                . StudentVerificationService::MAX_BATCH_SIZE . ' candidates.'
            );
        }

        $students = $this->studentModel->getStudentsByIds($studentIds);

        if ($students === []) {
            throw new \InvalidArgumentException('The selected student records could not be found.');
        }

        // Restore the legacy duplicate guard: never confirm a student twice.
        $alreadyConfirmed = $this->confirmationModel->getConfirmedStudentIds(
            array_column($students, 'id')
        );

        $pending = array_values(array_filter(
            $students,
            static fn (array $s): bool => ! in_array((int) $s['id'], $alreadyConfirmed, true)
        ));

        $skipped = count($students) - count($pending);

        if ($pending === []) {
            throw new \InvalidArgumentException(
                'All selected students already have an eligibility confirmation recorded.'
            );
        }

        $checklist = $this->mapChecklist($postData['checklist'] ?? [], array_column($pending, 'id'));

        $inserted = $this->confirmationModel->storeConfirmationBatch($pending, $checklist, $username);

        if ($inserted === 0) {
            throw new \RuntimeException('The confirmation batch could not be saved.');
        }

        $this->activityLogService->record(
            'confirmation.create',
            'conf_stud_data',
            null,
            "Confirmed eligibility for {$inserted} candidate(s)"
        );

        return ['count' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Sanitizes one student's checklist submission. Mirrors con_view.php's
     * server-side normalization: an untouched/"sel" dropdown always becomes
     * "No", never left blank, since mig_TC/p_degree/s_marks are always
     * meaningful Yes/No facts once a confirmation row exists at all.
     * "Confirmation From" fields are only meaningful when mig_tc is "Yes"
     * (mirrors the legacy form's own show/hide condition) -- silently
     * cleared otherwise, so a value entered before unchecking mig_tc can't
     * linger into the saved row.
     *
     * @param  array<int|string, array<string, string>> $rawChecklist
     * @param  int[] $studentIds
     * @return array<int, array<string, string>> student_id => sanitized fields
     */
    private function mapChecklist(array $rawChecklist, array $studentIds): array
    {
        $result = [];

        foreach ($studentIds as $studentId) {
            $raw = $rawChecklist[$studentId] ?? [];

            $migTc = ($raw['mig_tc'] ?? '') === 'Yes' ? 'Yes' : 'No';

            $confFrom = '';
            $confFromText = '';
            $confFromSelect = '';
            $etcData = '';

            if ($migTc === 'Yes') {
                $confFrom = in_array($raw['conf_from'] ?? '', self::CONF_FROM_OPTIONS, true) ? $raw['conf_from'] : '';
                $confFromText = $confFrom === 'other' ? sanitize_xss((string) ($raw['conf_from_text'] ?? '')) : '';
                $confFromSelect = in_array($raw['conf_from_select'] ?? '', self::CLARIFICATION_OPTIONS, true) ? $raw['conf_from_select'] : '';
                $etcData = in_array($raw['etc_data'] ?? '', self::NAME_CHANGE_OPTIONS, true) ? $raw['etc_data'] : '';
            }

            $result[$studentId] = [
                'mig_tc'           => $migTc,
                'p_degree'         => ($raw['p_degree'] ?? '') === 'Yes' ? 'Yes' : 'No',
                's_marks'          => ($raw['s_marks'] ?? '') === 'Yes' ? 'Yes' : 'No',
                'letter_no_date'   => sanitize_xss((string) ($raw['letter_no_date'] ?? '')),
                'remark'           => sanitize_xss((string) ($raw['remark'] ?? '')),
                'conf_from'        => $confFrom,
                'conf_from_text'   => $confFromText,
                'conf_from_select' => $confFromSelect,
                'etc_data'         => $etcData,
            ];
        }

        return $result;
    }

    public function getTotalConfirmedCount(): int
    {
        return $this->confirmationModel->getTotalConfirmedCount();
    }

    /**
     * Feeds the history/browse page -- one row per confirmed batch plus the
     * populated Pager. All filters optional; blank = unrestricted.
     *
     * Reads the pager off this Service's own $confirmationModel instance
     * right after calling it (paginate() only populates ->pager on whichever
     * instance actually ran the query), so the Controller never needs its
     * own separate Model handle to get at it.
     *
     * @return array{batches: array, pager: \CodeIgniter\Pager\PagerInterface}
     */
    public function getBatchSummaries(string $acdYear = '', string $stream = '', string $university = '', string $studentName = '', string $dateFrom = '', string $dateTo = ''): array
    {
        $batches = $this->confirmationModel->getBatchSummaries($acdYear, $stream, $university, $studentName, $dateFrom, $dateTo);

        return [
            'batches' => $batches,
            'pager'   => $this->confirmationModel->pager,
        ];
    }

    /** Same filters as getBatchSummaries() above, unpaginated -- feeds the Excel export. */
    public function getBatchSummariesAll(string $acdYear = '', string $stream = '', string $university = '', string $studentName = '', string $dateFrom = '', string $dateTo = ''): array
    {
        return $this->confirmationModel->getBatchSummariesAll($acdYear, $stream, $university, $studentName, $dateFrom, $dateTo);
    }

    /** One confirmed batch's full student detail, for the browse/expand view. */
    public function getBatchDetail(int $arraySpace): array
    {
        return $this->confirmationModel->getBatchDetail($arraySpace);
    }

    /**
     * One confirmation row by id.
     *
     * Needed because deleting a record has to know which batch it belonged to
     * BEFORE the row goes -- afterwards there is nothing left to derive it from,
     * and the reply has to carry that batch's re-rendered rows.
     */
    public function getConfirmationById(int $id): ?array
    {
        return $this->confirmationModel->find($id) ?: null;
    }

    /**
     * @throws \InvalidArgumentException when the record doesn't exist
     */
    public function deleteConfirmation(int $id): void
    {
        if (! feature_enabled('feature_delete_enabled')) {
            throw new \InvalidArgumentException('Deleting records is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        $record = $this->confirmationModel->find($id);
        if (! $record) {
            throw new \InvalidArgumentException('Confirmation record not found.');
        }

        $this->confirmationModel->deleteById($id);

        $this->activityLogService->record(
            'confirmation.delete',
            'conf_stud_data',
            $id,
            'Deleted confirmation record for case no. ' . ($record['case_no'] ?? $id)
        );
    }
}
