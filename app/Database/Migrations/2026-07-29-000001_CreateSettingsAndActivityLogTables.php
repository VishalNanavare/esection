<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Foundation for the Settings module: a generic key/value settings store and
 * an audit trail. Every subsequent Settings sub-feature (institute details,
 * academic years, courses, feature toggles, user management, document
 * numbering) writes into one or both of these tables rather than each
 * inventing its own schema.
 */
class CreateSettingsAndActivityLogTables extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('settings')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'setting_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'unique'     => true,
                ],
                'setting_value' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'setting_group' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'updated_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('settings');
        }

        if (! $this->db->tableExists('activity_log')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                // Denormalized: a user can be deactivated (or renamed) after
                // an entry is written. Keeping the name here means the log
                // stays readable, instead of a blank column for old history.
                'username' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'action' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'entity_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'entity_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('activity_log');
        }
    }

    public function down()
    {
        $this->forge->dropTable('activity_log', true);
        $this->forge->dropTable('settings', true);
    }
}
