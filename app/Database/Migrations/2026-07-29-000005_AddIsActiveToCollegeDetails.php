<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds is_active to college_details so universities can be deactivated
 * instead of deleted -- student_details stores a copied name/address at
 * creation time, not a foreign key to this table, so there is no clean way
 * to check whether a university is "in use" before a hard delete.
 *
 * The existing sel_data column is left untouched: it's VARCHAR(5), never
 * queried anywhere in the app today, and its real values across all 470
 * live rows aren't verified -- a clean typed column is the same low-risk
 * move already proven for `users` (2026-07-29-000004_AddIsActiveToUsers).
 */
class AddIsActiveToCollegeDetails extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('is_active', 'college_details')) {
            return;
        }

        $this->forge->addColumn('college_details', [
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('is_active', 'college_details')) {
            $this->forge->dropColumn('college_details', 'is_active');
        }
    }
}
