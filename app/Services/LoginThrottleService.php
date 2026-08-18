<?php

namespace App\Services;

use App\Models\LoginAttemptModel;

/**
 * Database-backed brute-force guard for the login form.
 *
 * Replaces CodeIgniter's cache-backed Throttler, which reset on any cache clear
 * and left nothing behind to review after the fact.
 *
 * Two independent counters, because the two attacks look different:
 *
 *   - PER IP catches one machine spraying many accounts.
 *   - PER USERNAME catches many machines guessing at one known account, which
 *     an IP-only guard cannot see at all.
 *
 * Either counter tripping blocks the attempt. Both are windowed and clear
 * themselves, so no operator ever needs to unlock anything -- and nobody can
 * lock a colleague out on purpose by failing their login, which is what a
 * permanent per-account lock would allow.
 *
 * The username threshold is deliberately the more generous of the two: a real
 * person mistyping their own password is the common case, and they should hit
 * the IP limit (shared with nobody else in a small office) long before their
 * account name becomes unusable from elsewhere.
 */
class LoginThrottleService
{
    /** Failed attempts from one IP before that IP is refused. */
    public const MAX_FAILURES_PER_IP = 5;

    /** Failed attempts against one username, from any IP, before it is refused. */
    public const MAX_FAILURES_PER_USERNAME = 10;

    /** How far back the counters look, in seconds. Also the cooldown length. */
    public const WINDOW_SECONDS = 900; // 15 minutes

    /** How long attempts are kept for review before housekeeping drops them. */
    public const RETENTION_DAYS = 30;

    /** Rows older than the window are purged roughly once every N attempts. */
    private const PURGE_PROBABILITY = 50;

    protected LoginAttemptModel $loginAttemptModel;

    public function __construct()
    {
        $this->loginAttemptModel = new LoginAttemptModel();
    }

    /**
     * Is this attempt allowed through?
     *
     * Called BEFORE the password is checked, so a blocked request never reaches
     * password_verify() -- the point is to stop the guessing, not to grade it.
     */
    public function isBlocked(string $ip, ?string $username): bool
    {
        return $this->isIpBlocked($ip) || $this->isUsernameBlocked($username);
    }

    /**
     * Has this SOURCE exhausted its attempts?
     *
     * A hard stop: the caller must refuse before reaching password_verify(),
     * so one machine spraying accounts cannot make the server spend a bcrypt
     * per guess.
     */
    public function isIpBlocked(string $ip): bool
    {
        return $this->loginAttemptModel->countRecentFailuresByIp($ip, $this->windowStart())
            >= self::MAX_FAILURES_PER_IP;
    }

    /**
     * Has this ACCOUNT NAME exhausted its attempts?
     *
     * Deliberately weaker than the IP counter, and the caller must not treat it
     * as a hard stop. This counter is the one an attacker can aim at somebody
     * else: fail a known username ten times every fifteen minutes from a couple
     * of addresses and it stays tripped for as long as they care to keep going,
     * while the person who owns the account -- typing the correct password from
     * their own desk -- is turned away with it.
     *
     * So the caller checks the password anyway and only refuses if it is wrong.
     * Someone who knows the password is not the attack this counter exists to
     * stop, and the per-IP limit still bounds how much bcrypt work any one
     * source can cause.
     */
    public function isUsernameBlocked(?string $username): bool
    {
        if ($username === null || $username === '') {
            return false;
        }

        return $this->loginAttemptModel->countRecentFailuresByUsername($username, $this->windowStart())
            >= self::MAX_FAILURES_PER_USERNAME;
    }

    /**
     * Record the outcome of an attempt.
     *
     * A success clears the failures that were counting against it, so someone
     * who mistypes a few times and then succeeds is not left one attempt from a
     * lockout of their own account.
     */
    public function record(string $ip, ?string $username, bool $successful): void
    {
        $this->loginAttemptModel->record($ip, $username, $successful);

        if ($successful) {
            $this->loginAttemptModel->clearFailures($ip, $username);
        }

        $this->maybePurge();
    }

    /**
     * Seconds until the oldest counted failure ages out.
     *
     * Used only for the message shown to the operator. Approximate on purpose:
     * telling someone "try again in about 12 minutes" is more useful than a
     * precise number, and it does not tell an attacker exactly when to resume.
     */
    public function retryAfterMinutes(): int
    {
        return (int) ceil(self::WINDOW_SECONDS / 60);
    }

    private function windowStart(): string
    {
        return date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);
    }

    /**
     * Occasional housekeeping, inline.
     *
     * A cron would be tidier, but scheduled tasks on this box need elevation
     * that is not available, and an unbounded table is a worse outcome than an
     * occasional extra DELETE. Probabilistic so the cost lands on roughly one
     * login in fifty rather than on every one.
     *
     * random_int(), not rand(): this runs on the login path, and using a
     * predictable PRNG anywhere near authentication invites the question of
     * what else uses one.
     */
    private function maybePurge(): void
    {
        try {
            if (random_int(1, self::PURGE_PROBABILITY) !== 1) {
                return;
            }

            $this->loginAttemptModel->purgeOlderThan(
                date('Y-m-d H:i:s', time() - (self::RETENTION_DAYS * 86400))
            );
        } catch (\Throwable $e) {
            // Housekeeping must never be the reason a login fails.
            log_message('error', '[LoginThrottleService::maybePurge] {msg}', ['msg' => $e->getMessage()]);
        }
    }
}
