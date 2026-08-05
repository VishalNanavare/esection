<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Self-service password reset. The token is stored HASHED (sha256), never
 * raw -- same principle as the password itself: anyone who can read this
 * table must not be able to forge a valid reset link from what they see.
 * Only the raw token, which never touches the database, goes out in the
 * email and is compared by re-hashing the value the user submits.
 */
class AddPasswordResetColumns extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('reset_token_hash', 'users')) {
            $this->forge->addColumn('users', [
                'reset_token_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'after'      => 'is_active',
                ],
                'reset_expires_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'reset_token_hash',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('reset_token_hash', 'users')) {
            $this->forge->dropColumn('users', ['reset_token_hash', 'reset_expires_at']);
        }
    }
}
