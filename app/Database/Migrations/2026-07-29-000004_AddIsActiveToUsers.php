<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds is_active to users so accounts can be deactivated instead of deleted
 * -- legacy en_by-style columns elsewhere reference usernames by value, not
 * by a foreign key, so a hard delete would leave those historical
 * references dangling.
 *
 * DEFAULT 1 on ADD COLUMN backfills every existing row to active; no
 * separate UPDATE is needed.
 */
class AddIsActiveToUsers extends Migration
{
    public function up()
    {
        if ($this->db->fieldExists('is_active', 'users')) {
            return;
        }

        $this->forge->addColumn('users', [
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
                'after'      => 'role',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->fieldExists('is_active', 'users')) {
            $this->forge->dropColumn('users', 'is_active');
        }
    }
}
