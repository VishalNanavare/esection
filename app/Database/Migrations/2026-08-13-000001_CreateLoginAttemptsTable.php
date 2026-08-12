<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Durable, auditable record of every login attempt, replacing the cache-backed
 * brute-force guard.
 *
 * CodeIgniter's Throttler kept its counters in the cache. That worked, but it
 * had two properties the office did not want: `php spark cache:clear` (or a
 * cache backend restart) silently reset every counter, and it left no trace, so
 * "was this account being guessed at last Tuesday?" was unanswerable.
 *
 * Both an IP and a username column, because the two attacks are different
 * shapes and neither guard catches the other:
 *
 *   - one IP against many accounts is a spray, caught by the IP counter;
 *   - many IPs against one account is a distributed guess at a known user,
 *     which an IP-only guard never sees.
 *
 * Nothing here ever locks an account permanently. Both counters are windowed,
 * so they age out on their own -- a permanent per-username lock would hand
 * anyone a way to lock a colleague out of the system just by failing their
 * login on purpose.
 *
 * The submitted password is deliberately absent, in any form. There is no
 * column for it and no hash of it: a table of password guesses is a table of
 * near-miss real passwords, and it would sit in every backup.
 */
class CreateLoginAttemptsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('login_attempts')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // 45 chars holds a full IPv6 address, including an IPv4-mapped one
            // ("::ffff:192.168.1.1"). VARCHAR(15) would have silently truncated.
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => false,
            ],
            // The username as TYPED, which is not necessarily one that exists --
            // that is the point: guesses against non-existent accounts are the
            // clearest brute-force signal there is. Nullable so a submission
            // with no username still records the IP.
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'successful' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'attempted_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);

        // The two lookups the throttle actually runs, each a composite in the
        // order the WHERE clause uses: bounded by time first in the query but
        // indexed by the selective column first, so MySQL can seek rather than
        // scan the whole window.
        $this->forge->addKey(['ip_address', 'attempted_at'], false, false, 'idx_login_attempts_ip_time');
        $this->forge->addKey(['username', 'attempted_at'], false, false, 'idx_login_attempts_user_time');

        // Used only by the housekeeping delete, which is bounded by time alone.
        $this->forge->addKey('attempted_at', false, false, 'idx_login_attempts_time');

        $this->forge->createTable('login_attempts', true);
    }

    public function down()
    {
        $this->forge->dropTable('login_attempts', true);
    }
}
