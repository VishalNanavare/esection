<?php

namespace App\Models;

use CodeIgniter\Model;

class UniversityReminderNoteModel extends Model
{
    protected $table            = 'university_reminder_notes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'batch_id', 'student_id', 'note_text', 'note_date', 'created_by', 'created_at',
    ];
    protected $useTimestamps    = false;

    /**
     * A fresh query builder. Not Model::builder() and not the Model's own
     * chainable methods -- both accumulate state on a shared instance, which
     * leaks between calls (same reasoning as CollegeModel/AcademicYearModel).
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    /** Every note for one batch, oldest first per student -- feeds the PDF's stacked history column. */
    public function getNotesForBatch(int $batchId): array
    {
        return $this->table()
                    ->where('batch_id', $batchId)
                    ->orderBy('student_id', 'ASC')
                    ->orderBy('id', 'ASC')
                    ->get()->getResultArray();
    }

    /** Every distinct student id that has at least one note in this batch. */
    public function getStudentIdsForBatch(int $batchId): array
    {
        $rows = $this->table()->select('student_id')->distinct()->where('batch_id', $batchId)->get()->getResultArray();

        return array_map(static fn (array $r): int => (int) $r['student_id'], $rows);
    }

    /** How many notes each of the given students already has, across ALL batches -- feeds the search results' status badge. */
    public function getNoteCountsForStudents(array $studentIds): array
    {
        $studentIds = array_values(array_filter(array_map('intval', $studentIds)));
        if ($studentIds === []) {
            return [];
        }

        $rows = $this->table()
                     ->select('student_id, COUNT(*) AS note_count')
                     ->whereIn('student_id', $studentIds)
                     ->groupBy('student_id')
                     ->get()->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['student_id']] = (int) $row['note_count'];
        }

        return $counts;
    }
}
