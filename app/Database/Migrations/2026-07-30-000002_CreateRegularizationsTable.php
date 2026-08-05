<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * esection_basic's Regularization workflow (reg_form.php -> config/reg_letter.php)
 * persisted every request into `reg_data`, letting staff browse, edit and reprint
 * letters later (reg_view.php / reg_update.php) and hard-delete a bad entry
 * (config/reg_delete.php -- the only hard-delete in the legacy app). The CI4
 * rewrite's Regularization::generateLetter() never persisted anything -- every
 * letter was a one-shot, unrecoverable PDF. This table restores that persistence.
 */
class CreateRegularizationsTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('regularizations')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'gender' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                    'null'       => true,
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
                'admission_letter_for' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 200,
                    'null'       => true,
                ],
                'admission_letter_date' => [
                    'type' => 'DATE',
                    'null' => true,
                ],
                'admission_taken_year' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => true,
                ],
                'admission_taken_in' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'university_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'passing_course' => [
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
            $this->forge->createTable('regularizations');
        }
    }

    public function down()
    {
        $this->forge->dropTable('regularizations', true);
    }
}
