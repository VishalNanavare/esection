<?php

namespace App\Services;

use App\Models\UniversityReminderBatchModel;
use App\Models\UniversityReminderNoteModel;
use App\Models\StudentModel;

class UniversityReminderService
{
    protected UniversityReminderBatchModel $batchModel;
    protected UniversityReminderNoteModel $noteModel;
    protected StudentModel $studentModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->batchModel          = new UniversityReminderBatchModel();
        $this->noteModel           = new UniversityReminderNoteModel();
        $this->studentModel        = new StudentModel();
        $this->activityLogService  = new ActivityLogService();
    }

    /** @return array<int, int> student_id => note_count, for the search results' status badge */
    public function getNoteCountsForStudents(array $studentIds): array
    {
        return $this->noteModel->getNoteCountsForStudents($studentIds);
    }

    /**
     * Finds the existing (academic_year, university_name) batch, or creates
     * one -- this is the grouping key a real registrar already thinks in
     * ("our ongoing reminder correspondence with this university, for this
     * year"), replacing legacy's opaque array_space bookkeeping.
     */
    private function findOrCreateBatch(string $year, string $university, string $course, string $headName, string $username): array
    {
        $existing = $this->batchModel->findByYearAndUniversity($year, $university);
        if ($existing) {
            return $existing;
        }

        $this->batchModel->insert([
            'academic_year'      => $year,
            'university_name'    => $university,
            'admission_taken_in' => $course,
            'head_name'          => $headName,
            'created_by'         => $username,
        ]);
        $newId = $this->batchModel->getInsertID();

        $this->activityLogService->record(
            'university_reminder.batch_create',
            'university_reminder_batches',
            $newId,
            "Started a reminder batch for {$university} ({$year})"
        );

        return $this->batchModel->find($newId);
    }

    /**
     * @throws \InvalidArgumentException on invalid input
     */
    public function addReminderNotes(array $postData, string $username): array
    {
        $studentIds = $postData['student_ids'] ?? [];
        if (empty($studentIds) || ! is_array($studentIds)) {
            throw new \InvalidArgumentException('No student records selected for reminder.');
        }

        $students = $this->studentModel->getStudentsByIds($studentIds);
        if ($students === []) {
            throw new \InvalidArgumentException('The selected student records could not be found.');
        }

        $noteText = sanitize_xss((string) ($postData['reminder_type'] ?? '1st Reminder'));
        $first    = $students[0];

        $batch = $this->findOrCreateBatch(
            (string) $first['admission_taken_year'],
            (string) $first['clg_add'],
            (string) $first['admission_taken_in'],
            sanitize_xss((string) ($postData['head_name'] ?? 'The Controller of Examinations')),
            $username
        );

        // One transaction over the whole set, matching how every other
        // multi-row write in this codebase is done. A failure partway through
        // the loop previously left the batch created and only some of its
        // candidates noted -- and because findOrCreateBatch() finds the
        // existing batch on a retry, the operator's second attempt appended
        // duplicate notes for everyone the first attempt had already written.
        $db = $this->noteModel->db;
        $db->transStart();

        $today = date('Y-m-d');
        foreach ($students as $student) {
            $this->noteModel->insert([
                'batch_id'   => (int) $batch['id'],
                'student_id' => (int) $student['id'],
                'note_text'  => $noteText,
                'note_date'  => $today,
                'created_by' => $username,
            ]);
        }

        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \RuntimeException('The reminder notes could not be saved. No changes were made.');
        }

        $this->activityLogService->record(
            'university_reminder.note_add',
            'university_reminder_batches',
            (int) $batch['id'],
            "Added '{$noteText}' for " . count($students) . " candidate(s)"
        );

        return ['batch_id' => (int) $batch['id']];
    }

    /** @param array<string,string> $filters see UniversityReminderBatchModel::getAllOrdered() */
    public function getBatchesSummary(array $filters = []): array
    {
        $batches = $this->batchModel->getAllOrdered($filters);

        // One grouped query for every batch's count, instead of one query per
        // rendered row. The per-row call materialised the student ids purely
        // to count them, and the ids were then discarded.
        $counts = $this->noteModel->getStudentCountsByBatch();

        foreach ($batches as &$batch) {
            $batch['student_count'] = $counts[(int) $batch['id']] ?? 0;
        }
        unset($batch);

        return $batches;
    }

    /**
     * @return array{batch: array, students: array} students each carry their
     *         own `notes` list, for the browse view and the reprint PDF.
     */
    public function getBatchDetail(int $batchId): array
    {
        $batch = $this->batchModel->find($batchId);
        if (! $batch) {
            throw new \InvalidArgumentException('Reminder batch not found.');
        }

        $notes      = $this->noteModel->getNotesForBatch($batchId);
        $studentIds = array_values(array_unique(array_column($notes, 'student_id')));
        $students   = $this->studentModel->getStudentsByIds($studentIds);

        $notesByStudent = [];
        foreach ($notes as $note) {
            $notesByStudent[(int) $note['student_id']][] = $note;
        }

        foreach ($students as &$student) {
            $student['notes'] = $notesByStudent[(int) $student['id']] ?? [];
        }

        return ['batch' => $batch, 'students' => $students];
    }
}
