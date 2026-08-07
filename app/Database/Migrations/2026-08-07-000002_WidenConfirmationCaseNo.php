<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Widen conf_stud_data.case_no so it can hold the value it is copied from.
 *
 * ConfirmationModel::storeConfirmationBatch() copies
 * student_details.eligibility_case_no (VARCHAR 60) verbatim into
 * conf_stud_data.case_no (VARCHAR 30). Anything longer than 30 characters is
 * silently cut in half -- silently because Config\Database::$default has
 * strictOn = false, so CodeIgniter strips STRICT_TRANS_TABLES from the
 * session and MySQL downgrades the overflow from an error to a warning
 * nobody reads (audit DATA-01).
 *
 * This is not theoretical. Measured on live data at migration time:
 *   - 22 students hold a case number longer than 30 characters
 *     (longest 51, e.g. "M.A. (Geography) -I     IDOL222361780")
 *   - 2 confirmation rows are already provable truncations -- their stored
 *     case_no is a strict prefix of the student's real one
 * Confirming any of the remaining 22 would corrupt another row. The case
 * number is how staff look a candidate up, so a half-stored one is a record
 * that cannot be found.
 *
 * Widening is the minimal fix and the safest possible DDL: no data is lost
 * or rewritten, every existing value stays byte-identical, and no query,
 * index or application code changes -- rows that used to truncate simply
 * store completely. 60 matches the source column exactly, so the two can no
 * longer disagree.
 *
 * down() narrows back to 30. That is genuinely lossy for any value longer
 * than 30 characters, so it is written to fail loudly rather than silently
 * destroy data if it is ever run against widened rows.
 */
class WidenConfirmationCaseNo extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('conf_stud_data')) {
            return;
        }

        $this->forge->modifyColumn('conf_stud_data', [
            'case_no' => [
                'name'       => 'case_no',
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
        ]);
    }

    public function down()
    {
        if (! $this->db->tableExists('conf_stud_data')) {
            return;
        }

        $tooLong = $this->db->table('conf_stud_data')
                            ->where('CHAR_LENGTH(case_no) >', 30)
                            ->countAllResults();

        if ($tooLong > 0) {
            throw new \RuntimeException(
                'Refusing to narrow conf_stud_data.case_no: ' . $tooLong
                . ' row(s) hold a value longer than 30 characters and would be truncated.'
            );
        }

        $this->forge->modifyColumn('conf_stud_data', [
            'case_no' => [
                'name'       => 'case_no',
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
        ]);
    }
}
