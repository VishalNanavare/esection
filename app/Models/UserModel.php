<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'username', 'email', 'password_hash', 'role', 'full_name', 'is_active',
        'reset_token_hash', 'reset_expires_at', 'created_at', 'updated_at',
    ];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    public function findByUsername(string $username): ?array
    {
        $res = $this->where('username', $username)->first();
        return $res ?: null;
    }

    public function getAllOrdered(): array
    {
        return $this->orderBy('username', 'ASC')->findAll();
    }

    /**
     * Access Rights only ever governs `staff` accounts (admin bypasses every
     * check unconditionally) -- the role filter is a query here, per the
     * Pure Model Query Rule, not something callers filter in PHP.
     */
    public function getAllStaffOrdered(): array
    {
        return $this->where('role', 'staff')->orderBy('username', 'ASC')->findAll();
    }

    /**
     * Dummy bcrypt hash of a value no user can supply. Used to keep the
     * failure path's timing comparable to the success path so the response
     * time does not reveal whether a username exists.
     */
    private const DUMMY_HASH = '$2y$12$usesomesillystringfoeX7Ic0zXQhhFCFtDaZQ8ojmSNMj/mHKbBK';

    /**
     * Verify a username/password pair against the stored bcrypt hash.
     *
     * This is the ONLY accepted credential path. There are deliberately no
     * fallbacks: earlier revisions accepted `$password === $username` and
     * synthesised phantom users with hardcoded ids (1 and 99) that did not
     * exist in the database, which then got stamped onto inserted records.
     */
    public function authenticateUser(string $username, string $password): ?array
    {
        $user = $this->findByUsername($username);

        if ($user === null) {
            password_verify($password, self::DUMMY_HASH);
            return null;
        }

        if (! password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            return null;
        }

        // A deactivated account gets the same generic failure as a wrong
        // password -- a distinct "your account is disabled" message would
        // leak account-exists-but-disabled to anyone probing usernames.
        if ((int) ($user['is_active'] ?? 1) === 0) {
            return null;
        }

        unset($user['password_hash']);

        return $user;
    }

    public function findByEmail(string $email): ?array
    {
        $res = $this->where('email', $email)->first();

        return $res ?: null;
    }

    /** Active user for a given (already-hashed) reset token, if unexpired. */
    public function findByValidResetTokenHash(string $tokenHash): ?array
    {
        $res = $this->where('reset_token_hash', $tokenHash)
                    ->where('is_active', 1)
                    ->where('reset_expires_at >=', date('Y-m-d H:i:s'))
                    ->first();

        return $res ?: null;
    }

    public function setResetToken(int $id, string $tokenHash, string $expiresAt): void
    {
        $this->update($id, ['reset_token_hash' => $tokenHash, 'reset_expires_at' => $expiresAt]);
    }

    public function clearResetToken(int $id): void
    {
        $this->update($id, ['reset_token_hash' => null, 'reset_expires_at' => null]);
    }
}
