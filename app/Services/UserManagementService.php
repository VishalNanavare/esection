<?php

namespace App\Services;

use App\Models\UserModel;

class UserManagementService
{
    /**
     * Minimum password length, shared by every admin-side write path.
     * Kept identical to PasswordResetService's self-service rule so the
     * two cannot drift apart again.
     */
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * Maximum password length.
     *
     * Requested by the office. Worth recording plainly that an upper bound this
     * low is the one password rule that reduces security rather than adding to
     * it -- it rules out passphrases and leaves an 8-10 character window. It is
     * applied here, in PasswordResetService and in the self-service change so
     * that no path can set a password another path would refuse; a split rule
     * would let an admin issue a password its owner could never re-enter.
     *
     * Existing stored passwords are untouched. This is checked only when a new
     * password is being set, so nobody is locked out by the change.
     */
    public const MAX_PASSWORD_LENGTH = 10;

    protected UserModel $userModel;
    protected ActivityLogService $activityLogService;
    protected AccessRightsService $accessRightsService;

    public function __construct()
    {
        helper('esection');
        $this->userModel            = new UserModel();
        $this->activityLogService   = new ActivityLogService();
        $this->accessRightsService  = new AccessRightsService();
    }

    public function getAll(): array
    {
        return $this->userModel->getAllOrdered();
    }

    public function getById(int $id): ?array
    {
        $res = $this->userModel->find($id);
        return $res ?: null;
    }

    /**
     * @throws \InvalidArgumentException on bad input
     */
    public function create(array $postData): void
    {
        $username = trim(sanitize_xss($postData['username'] ?? ''));
        $password = (string) ($postData['password'] ?? '');
        $role     = ($postData['role'] ?? '') === 'admin' ? 'admin' : 'staff';

        if ($username === '') {
            throw new \InvalidArgumentException('Username is required.');
        }
        if ($password === '') {
            throw new \InvalidArgumentException('Password is required.');
        }
        $this->assertPasswordStrength($password);
        if ($this->userModel->findByUsername($username)) {
            throw new \InvalidArgumentException('That username is already in use.');
        }

        $data = [
            'username'      => $username,
            'email'         => trim(sanitize_xss($postData['email'] ?? '')),
            'full_name'     => trim(sanitize_xss($postData['full_name'] ?? '')),
            'role'          => $role,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'is_active'     => 1,
        ];

        $this->userModel->insert($data);
        $newId = $this->userModel->getInsertID();

        // Access Rights only ever governs staff accounts (admin bypasses
        // every check). pages_submitted is a hidden marker the Add User
        // modal always sends alongside its 6 page checkboxes -- its PRESENCE
        // (not the checkbox values themselves) is what distinguishes "an
        // admin explicitly configured this user's pages" from "some other
        // caller that doesn't know about this feature", since a fully
        // unchecked checkbox group is otherwise indistinguishable from an
        // absent field.
        if ($role === 'staff') {
            if (array_key_exists('pages_submitted', $postData)) {
                $this->accessRightsService->saveGrantsForUser($newId, $postData['pages'] ?? [], (int) session()->get('id'));
            } else {
                $this->accessRightsService->ensureDefaultGrants($newId);
            }
        }

        $this->activityLogService->record('user.create', 'user', $newId, 'Created user ' . $username);
    }

    /**
     * @throws \InvalidArgumentException on bad input, or when the acting
     *         user tries to demote their own account away from admin
     */
    public function update(int $id, array $postData, ?int $actingUserId): void
    {
        $user = $this->getById($id);
        if (! $user) {
            throw new \InvalidArgumentException('User not found.');
        }

        $username = trim(sanitize_xss($postData['username'] ?? ''));
        if ($username === '') {
            throw new \InvalidArgumentException('Username is required.');
        }

        $existing = $this->userModel->findByUsername($username);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new \InvalidArgumentException('That username is already in use.');
        }

        $role = ($postData['role'] ?? '') === 'admin' ? 'admin' : 'staff';

        // Checked against the SESSION's acting-user id, never a client
        // -supplied field, so a tampered hidden input can't bypass this.
        if ($id === $actingUserId && $role !== 'admin') {
            throw new \InvalidArgumentException('You cannot demote your own account while logged in.');
        }

        $data = [
            'username'  => $username,
            'email'     => trim(sanitize_xss($postData['email'] ?? '')),
            'full_name' => trim(sanitize_xss($postData['full_name'] ?? '')),
            'role'      => $role,
        ];

        // Password optional on edit: blank/absent means keep the existing
        // hash. Never hash an empty string over it.
        $password = (string) ($postData['password'] ?? '');
        if ($password !== '') {
            $this->assertPasswordStrength($password);
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);

            // Kill any outstanding emailed reset link, exactly as
            // changeOwnPassword() and PasswordResetService::resetWithToken() do.
            //
            // This was the one password-writing path that still missed it, and
            // it is the one an administrator actually uses during an incident:
            // "someone may have my account" -> admin sets a new password here.
            // Without this, a reset link the attacker had already triggered
            // stayed valid for the rest of its hour, so they could simply set
            // the password again and undo the remediation.
            $data['reset_token_hash'] = null;
            $data['reset_expires_at'] = null;
        }

        $this->userModel->update($id, $data);

        // Same pages_submitted-gated logic as create() above. The "no
        // marker" branch also covers an admin demoted to staff by a caller
        // that predates this feature -- ensureDefaultGrants()'s "only if
        // zero rows" guard means it never clobbers an existing curated set,
        // it only ever fills in a genuinely ungranted account.
        if ($role === 'staff') {
            if (array_key_exists('pages_submitted', $postData)) {
                $this->accessRightsService->saveGrantsForUser($id, $postData['pages'] ?? [], $actingUserId);
            } else {
                $this->accessRightsService->ensureDefaultGrants($id);
            }
        }

        $this->activityLogService->record('user.update', 'user', $id, 'Updated user ' . $username);
    }

    /**
     * @throws \InvalidArgumentException when the user doesn't exist, or the
     *         acting user tries to deactivate their own account
     */
    public function toggleActive(int $id, ?int $actingUserId): void
    {
        $user = $this->getById($id);
        if (! $user) {
            throw new \InvalidArgumentException('User not found.');
        }

        $newState = ((int) $user['is_active']) ? 0 : 1;

        if ($id === $actingUserId && $newState === 0) {
            throw new \InvalidArgumentException('You cannot deactivate your own account while logged in.');
        }

        $this->userModel->update($id, ['is_active' => $newState]);

        $this->activityLogService->record(
            'user.toggle_active',
            'user',
            $id,
            ($newState ? 'Activated' : 'Deactivated') . ' user ' . $user['username']
        );
    }

    /**
     * The one password rule, applied at every point an admin sets one.
     *
     * Previously the three write paths disagreed: "Add User" accepted any
     * non-empty string (a one-character password was hashed and stored),
     * "Edit User" checked nothing beyond non-blank, and only the
     * self-service reset required 8 characters. So the strongest rule
     * guarded the path an attacker does not control, and the weakest
     * guarded the accounts an administrator creates.
     *
     * Deliberately just a length floor, matching
     * PasswordResetService::resetWithToken() exactly rather than inventing
     * a stricter composition rule -- the two paths must agree, and adding
     * character-class requirements here would be a workflow change nobody
     * asked for.
     *
     * Existing stored passwords are untouched: this runs only when a new
     * one is being set, so no user is locked out and nobody is forced to
     * reset.
     */
    private function assertPasswordStrength(string $password): void
    {
        self::assertPasswordPolicy($password);
    }

    /**
     * The single password rule for the whole application.
     *
     * Static and public so PasswordResetService and the self-service change can
     * call the same code rather than restating the bounds -- the previous
     * arrangement had the length hardcoded in two places and they had already
     * been noted as at risk of drifting.
     *
     * mb_strlen(), not strlen(): a password containing any non-ASCII character
     * would otherwise be measured in bytes and rejected for being "too long"
     * while looking well inside the limit to the person typing it.
     *
     * @throws \InvalidArgumentException when the password is outside the policy
     */
    public static function assertPasswordPolicy(string $password): void
    {
        $length = mb_strlen($password);

        if ($length < self::MIN_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters long.'
            );
        }

        if ($length > self::MAX_PASSWORD_LENGTH) {
            throw new \InvalidArgumentException(
                'Password must be no more than ' . self::MAX_PASSWORD_LENGTH . ' characters long.'
            );
        }
    }

    /**
     * Change your own password.
     *
     * Requires the current password: without it, anyone reaching an unattended
     * logged-in browser could take the account over silently. That is also what
     * separates this from the emailed reset link, where possession of the token
     * is the proof.
     *
     * @throws \InvalidArgumentException on a wrong current password, a mismatch
     *                                   or a password outside the policy
     */
    public function changeOwnPassword(int $userId, string $current, string $new, string $confirm): void
    {
        $user = $this->userModel->find($userId);

        if (! $user) {
            throw new \InvalidArgumentException('Your account could not be found. Please sign in again.');
        }

        if ($current === '' || ! password_verify($current, $user['password_hash'])) {
            // Deliberately not "wrong password" vs "no password given": both are
            // the same failure to anyone probing this endpoint.
            throw new \InvalidArgumentException('Your current password is not correct.');
        }

        if ($new !== $confirm) {
            throw new \InvalidArgumentException('The two new passwords do not match.');
        }

        if ($new === $current) {
            throw new \InvalidArgumentException('The new password must be different from your current one.');
        }

        self::assertPasswordPolicy($new);

        $this->userModel->update($userId, [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            // Any outstanding emailed reset link dies with the old password.
            //
            // Without this, someone who requested a reset link and then changed
            // their password another way -- the usual "I think my account is
            // compromised" reaction -- left that link live for the rest of its
            // hour. Whoever held the email could still walk in and set a new
            // password, which is the exact scenario the change was made to
            // prevent. PasswordResetService::resetWithToken() clears both on
            // its own path; this one has to as well.
            'reset_token_hash' => null,
            'reset_expires_at' => null,
        ]);

        $this->activityLogService->record(
            'user.password_self_change',
            'user',
            $userId,
            'Changed their own password'
        );
    }
}
