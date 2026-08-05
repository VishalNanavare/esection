<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Every email the system sends, with its delivery outcome.
 *
 * This exists to answer one question the office cannot answer today: "did
 * this student/university actually get the reminder?" Without a log, staff
 * argue from memory. Failures keep their error text so they can be retried
 * and diagnosed rather than silently lost.
 */
class CreateEmailLogTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('email_log')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                // Which send this belonged to, so a whole run can be reviewed
                // or retried as a unit.
                'batch_ref' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => true,
                ],
                'template_slug' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => true,
                ],
                // 'university' | 'student' -- what the recipient is.
                'recipient_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
                'recipient_id' => [
                    'type'     => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null'     => true,
                ],
                'recipient_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 200,
                    'null'       => true,
                ],
                'recipient_email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 190,
                ],
                'subject' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                // 'sent' | 'failed'
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                ],
                'error_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'attempts' => [
                    'type'       => 'INT',
                    'constraint' => 4,
                    'unsigned'   => true,
                    'default'    => 1,
                ],
                'sent_by' => [
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
            // The send-log page filters on these three.
            $this->forge->addKey('status');
            $this->forge->addKey('batch_ref');
            $this->forge->addKey('created_at');
            $this->forge->createTable('email_log');
        }
    }

    public function down()
    {
        $this->forge->dropTable('email_log', true);
    }
}
