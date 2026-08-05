<?php

namespace App\Services;

use App\Models\UserModel;

class UserManagementService
{
    protected UserModel $userModel;
    protected ActivityLogService $activityLogService;
    protected AccessRightsService $accessRightsService;

    public function __construct()
    {
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
            $data['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
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
}
