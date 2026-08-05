<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Backup history -- one row per SUCCESSFUL backup file produced by
 * BackupService. There is deliberately no `status` column: a backup that
 * fails any of its integrity gates never gets a row at all, so the mere
 * existence of a row means "this file was written and verified". A history
 * list that can show failures invites treating a failed run as a backup.
 *
 * `created_by` stores the username rather than the user id, matching
 * student_reminders.created_by and activity_log.username -- history must stay
 * readable after a user is renamed or deactivated.
 */
class CreateBackupHistoryTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('backup_history')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                // Basename only, never a path -- the download action rebuilds
                // the full path from a trusted constant, so a stored path
                // could never be used to escape the backup directory.
                'filename' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 10,
                ],
                'file_size' => [
                    'type'     => 'BIGINT',
                    'unsigned' => true,
                    'null'     => true,
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
            // Both the history list and the retention prune order by this.
            $this->forge->addKey('created_at');
            $this->forge->createTable('backup_history');
        }
    }

    public function down()
    {
        $this->forge->dropTable('backup_history', true);
    }
}
