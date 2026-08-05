<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Data-quality backfill, not a schema change: 6274 of 6345 student_details rows
 * (98.9%) have clg_add set to a bare numeric college_details.id instead of the
 * university's real postal address -- confirmed these are historical (latest
 * affected row dates to 2026-03-14, well before this rewrite's active development
 * began), not an ongoing defect in the current student-entry form (verified
 * directly: today's Students::getCollegeInfo() + storeBatch() correctly persist
 * the real address end to end).
 *
 * Every place that reads student_details.clg_add (dispatch letters, DD
 * Confirmation, both Reminder flows) displays/prints it directly as a
 * human-readable address, so these 6274 rows have been showing a bare number
 * (e.g. "34") instead of a real address on official letters. Idempotent: only
 * touches rows still holding a pure-numeric value, safe to re-run.
 */
class BackfillStudentClgAddFromCollegeId extends Migration
{
    public function up()
    {
        $this->db->query(
            "UPDATE student_details sd
             JOIN college_details cd ON cd.id = CAST(sd.clg_add AS UNSIGNED)
             SET sd.clg_add = cd.Address
             WHERE sd.clg_add REGEXP '^[0-9]+$'"
        );
    }

    public function down()
    {
        // Not reversible: the original numeric ids are not recoverable once
        // overwritten with the resolved address text.
    }
}
