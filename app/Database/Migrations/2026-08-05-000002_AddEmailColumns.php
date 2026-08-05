<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Bulk email had no recipients to send to: student_details carried no email
 * column at all (6,345 rows, zero addresses), and while college_details
 * already had `email_id`, only 60 of 469 rows were populated.
 *
 * Adds student_details.email so candidate addresses can be captured from the
 * entry/edit forms going forward. Existing rows stay NULL -- there is no data
 * to backfill from, and BulkEmailService skips any recipient without a valid
 * address rather than guessing.
 *
 * college_details.email_id is left exactly as it is (already present, already
 * the right type); only the Universities form is updated so staff can fill in
 * the missing 409.
 */
class AddEmailColumns extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('email', 'student_details')) {
            $this->forge->addColumn('student_details', [
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 190,
                    'null'       => true,
                    'after'      => 'student_nee_name',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('email', 'student_details')) {
            $this->forge->dropColumn('student_details', 'email');
        }
    }
}
