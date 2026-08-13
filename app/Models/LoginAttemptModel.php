<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Login attempt log, backing the brute-force guard.
 *
 * Follows the private table() idiom used across this app's models rather than
 * the shared builder(): each call starts from a fresh builder, so a WHERE from
 * one query can never leak into the next.
 */
class LoginAttemptModel extends Model
{
    protected $table         = 'login_attempts';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['ip_address', 'username', 'successful', 'attempted_at'];

    // Written explicitly in record() so the value is the same clock the counting
    // queries compare against.
    protected $useTimestamps = false;

    private function table()
    {
        return $this->db->table($this->table);
    }

    public function record(string $ip, ?string $username, bool $successful): void
    {
        $this->table()->insert([
            'ip_address'   => $ip,
            'username'     => $username !== null && $username !== '' ? mb_substr($username, 0, 150) : null,
            'successful'   => $successful ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Failed attempts from one IP since $since. */
    public function countRecentFailuresByIp(string $ip, string $since): int
    {
        return $this->table()
            ->where('ip_address', $ip)
            ->where('successful', 0)
            ->where('attempted_at >=', $since)
            ->countAllResults();
    }

    /** Failed attempts against one username since $since, from any IP. */
    public function countRecentFailuresByUsername(string $username, string $since): int
    {
        return $this->table()
            ->where('username', $username)
            ->where('successful', 0)
            ->where('attempted_at >=', $since)
            ->countAllResults();
    }

    /**
     * Clear the failures that were counting against a successful sign-in.
     *
     * Without this, someone who mistypes four times and then gets it right
     * would still be one attempt away from being locked out of their own
     * account for the rest of the window.
     */
    public function clearFailures(string $ip, ?string $username): void
    {
        // Scoped to the username that just authenticated, and nothing else.
        //
        // This used to be `(ip_address = ? OR username = ?)`, which meant one
        // successful sign-in erased every failure logged from that IP against
        // OTHER accounts. Anyone holding a single valid login could therefore
        // guess at a colleague's password indefinitely: fail nine times, log
        // into their own account to wipe the IP's history, repeat. The per-IP
        // counter could never accumulate.
        //
        // Proving you own account A says nothing about the failures recorded
        // against account B, even from the same machine, so only A's are
        // cleared. Those rows carry this IP's failures for A too, so an honest
        // user who mistypes a few times and then succeeds still walks away with
        // a clean slate.
        if ($username === null || $username === '') {
            return;
        }

        $this->table()
            ->where('successful', 0)
            ->where('username', $username)
            ->delete();
    }

    /** Housekeeping: drop rows older than the audit horizon. */
    public function purgeOlderThan(string $cutoff): int
    {
        $this->table()->where('attempted_at <', $cutoff)->delete();

        return $this->db->affectedRows();
    }

    /** Most recent attempts, newest first -- for an admin looking at an incident. */
    public function recent(int $limit = 100): array
    {
        return $this->table()
            ->orderBy('attempted_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();
    }
}
