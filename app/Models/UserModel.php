<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['username', 'email', 'password_hash', 'role', 'full_name', 'created_at', 'updated_at'];
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';

    public function findByUsername(string $username): ?array
    {
        $res = $this->where('username', $username)->first();
        return $res ?: null;
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

        unset($user['password_hash']);

        return $user;
    }
}
