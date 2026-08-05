<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * esection_basic's Student Reminders workflow (student_rem_page.php ->
 * config/student_rem_pdf.php) persisted every letter into `student_rem`, letting
 * staff browse and reprint later (student_rem_view.php). CI4's rewrite never
 * persisted anything -- every "generate" was a one-shot, unrecoverable PDF.
 *
 * Simpler than Regularization/University Reminders: legacy itself never supported
 * editing or deleting a student_rem row, only creating and viewing/reprinting --
 * matched here (delete added anyway, for consistency with the other 2 reminder
 * flows' history pages, but no edit UI).
 */
class CreateStudentRemindersTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('student_reminders')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'student_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 200,
                ],
                'eligibility_case_no' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'course_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'missing_doc' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'created_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('student_reminders');
        }
    }

    public function down()
    {
        $this->forge->dropTable('student_reminders', true);
    }
}
