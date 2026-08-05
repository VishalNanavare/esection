<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * esection_basic's University Reminders workflow (rem_page.php -> reme_view.php ->
 * config/new_rem_ins.php) persisted every reminder round into `rem_db`'s flat
 * eligib1-4/dated1-4/pdf_time1-4 columns, letting staff browse/reprint a batch's
 * full reminder history (rem_view.php / config/rem_conf.php). CI4's rewrite never
 * persisted anything -- every "generate" was a one-shot, unrecoverable PDF with no
 * multi-note history at all.
 *
 * Normalized here (one row per reminder note) rather than porting legacy's flat
 * 4-slot columns verbatim: legacy hard-capped reminder rounds at 4 and its
 * insert-vs-update decision (config/new_rem_ins.php) keyed off a student's *global*
 * rem_db row regardless of which batch it belonged to, which could silently scatter
 * one submission's students across different array_space groups. A batch here is
 * keyed by (academic_year, university_name) -- the natural "ongoing reminder
 * relationship with this university for this year" grouping -- found-or-created on
 * each submission rather than passed around as an encoded array_space.
 */
class CreateUniversityReminderTables extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('university_reminder_batches')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'academic_year' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                ],
                'university_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'admission_taken_in' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'head_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
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
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['academic_year', 'university_name']);
            $this->forge->createTable('university_reminder_batches');
        }

        if (! $this->db->tableExists('university_reminder_notes')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'batch_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'student_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'note_text' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'note_date' => [
                    'type' => 'DATE',
                    'null' => true,
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
            $this->forge->addKey(['batch_id', 'student_id']);
            $this->forge->createTable('university_reminder_notes');
        }
    }

    public function down()
    {
        $this->forge->dropTable('university_reminder_notes', true);
        $this->forge->dropTable('university_reminder_batches', true);
    }
}
