<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Index the column every batch screen looks records up by.
 *
 * `array_space` is the batch grouping key: it is the WHERE clause behind
 * Students > Batch Detail, Confirmation Batch Detail, both dispatch PDFs
 * and the batch-history GROUP BY. Despite that, `student_details` carried
 * no index at all beyond its PRIMARY key, and `conf_stud_data` indexed
 * only `student_id`, so every one of those lookups was a full table scan:
 *
 *   EXPLAIN SELECT * FROM student_details WHERE array_space='esection1_7546';
 *     -> Table scan on student_details (cost=630 rows=6051)
 *   EXPLAIN SELECT * FROM conf_stud_data WHERE array_space='5762';
 *     -> Table scan on conf_stud_data (cost=455 rows=4310)
 *
 * The cost is paid on the app's most-used screens and grows linearly with
 * the table, so it gets worse every intake.
 *
 * Index-only: no column is added, dropped, retyped or reordered, and no
 * query is rewritten. Results and their ordering are identical -- the
 * planner simply stops reading every row to find a handful. This is
 * transparent to the application, which is why it needs no code change.
 *
 * Non-unique on purpose: a batch is by definition many rows sharing one
 * array_space (113 in the largest current batch), so a UNIQUE index would
 * reject legitimate data.
 *
 * down() drops both, so this is fully reversible.
 */
class AddBatchLookupIndexes extends Migration
{
    public function up()
    {
        // Guarded so a re-run cannot fail on an already-present index --
        // matching the tableExists() guards the other migrations use.
        if (! $this->indexExists('student_details', 'idx_student_array_space')) {
            $this->db->query('CREATE INDEX idx_student_array_space ON student_details (array_space)');
        }

        if (! $this->indexExists('conf_stud_data', 'idx_conf_array_space')) {
            $this->db->query('CREATE INDEX idx_conf_array_space ON conf_stud_data (array_space)');
        }
    }

    public function down()
    {
        if ($this->indexExists('student_details', 'idx_student_array_space')) {
            $this->db->query('DROP INDEX idx_student_array_space ON student_details');
        }

        if ($this->indexExists('conf_stud_data', 'idx_conf_array_space')) {
            $this->db->query('DROP INDEX idx_conf_array_space ON conf_stud_data');
        }
    }

    /**
     * CI4's Forge has no "does this index exist" check, and SHOW INDEX is
     * the portable-enough answer for the one engine this app runs on.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        if (! $this->db->tableExists($table)) {
            return false;
        }

        $rows = $this->db->query(
            'SHOW INDEX FROM ' . $this->db->protectIdentifiers($table, true) . ' WHERE Key_name = ?',
            [$indexName]
        )->getResultArray();

        return $rows !== [];
    }
}
