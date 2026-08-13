<?php

namespace App\Services;

use App\Models\AccessPageModel;
use App\Models\UserModel;
use App\Models\UserPageAccessModel;
use Config\Permissions;

/**
 * Owns the page-level Access Rights system introduced 2026-07-30. Only ever
 * governs `staff` accounts -- `role === 'admin'` bypasses every check
 * unconditionally (see AccessFilter) and never has rows here.
 */
class AccessRightsService
{
    // PAGE_DEFINITIONS is gone. It was a second copy of the catalog that had to
    // be kept in step with the access_pages table and with a hardcoded string in
    // Config\Routes by hand -- and when they drifted, a key present in the table
    // but missing here rendered UNCHECKED for users who actually held it, so the
    // next save revoked it from everyone. Config\Permissions is now the only
    // source of truth, and access_pages is seeded from it.

    protected AccessPageModel $accessPageModel;
    protected UserPageAccessModel $userPageAccessModel;
    protected UserModel $userModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->accessPageModel     = new AccessPageModel();
        $this->userPageAccessModel = new UserPageAccessModel();
        $this->userModel           = new UserModel();
        $this->activityLogService  = new ActivityLogService();
    }

    public function getAllPages(): array
    {
        return $this->accessPageModel->getAllOrdered();
    }

    public function getPagesForUser(int $userId): array
    {
        return $this->userPageAccessModel->getPageKeysForUser($userId);
    }

    /**
     * Feeds the Access Rights matrix: one row per staff user, each carrying
     * a page_key => bool map for every known page.
     *
     * @return array<int, array{id: int, username: string, full_name: string, pages: array<string, bool>}>
     */
    public function getAllUsersWithGrants(): array
    {
        $staff   = $this->userModel->getAllStaffOrdered();
        $grouped = $this->userPageAccessModel->getGrantsGroupedByUser();
        $pageKeys = Permissions::allKeys();

        $result = [];
        foreach ($staff as $user) {
            $granted = $grouped[(int) $user['id']] ?? [];
            $pages   = [];
            foreach ($pageKeys as $pageKey) {
                $pages[$pageKey] = in_array($pageKey, $granted, true);
            }

            $result[] = [
                'id'        => (int) $user['id'],
                'username'  => $user['username'],
                'full_name' => $user['full_name'] ?? '',
                'pages'     => $pages,
            ];
        }

        return $result;
    }

    /**
     * Revokes every grant a user holds.
     *
     * Used when an account is promoted to admin. Admin bypasses AccessFilter by
     * role and is meant to hold ZERO grant rows, but update() previously just
     * skipped grant handling for admins -- so a staff user's rows survived the
     * promotion, invisible to every screen (the modal hides the chips for
     * admins) and silently reinstated in full if the account was ever demoted
     * back to staff.
     */
    public function clearGrantsForUser(int $userId, ?int $actingUserId): void
    {
        if ($this->userPageAccessModel->getPageKeysForUser($userId) === []) {
            return;
        }

        $this->userPageAccessModel->replaceGrantsForUser($userId, [], $actingUserId);

        $user = $this->userModel->find($userId);
        $this->activityLogService->record(
            'access_rights.update',
            'user_page_access',
            $userId,
            'Cleared page access for: ' . ($user['username'] ?? "user #{$userId}") . ' (promoted to admin)'
        );
    }

    /**
     * Single-user save ONLY -- touches nothing but $userId's own grants.
     * Used by the Users Add/Edit modal hook, never the bulk matrix.
     *
     * @param array<int, string> $pageKeys
     * @throws \InvalidArgumentException on an unknown page_key
     */
    public function saveGrantsForUser(int $userId, array $pageKeys, ?int $actingUserId): void
    {
        $validated = $this->validatePageKeys($pageKeys);
        $before    = $this->userPageAccessModel->getPageKeysForUser($userId);

        sort($before);
        $sortedRequested = $validated;
        sort($sortedRequested);

        if ($before === $sortedRequested) {
            return;
        }

        $this->userPageAccessModel->replaceGrantsForUser($userId, $validated, $actingUserId);

        $user = $this->userModel->find($userId);
        $this->activityLogService->record(
            'access_rights.update',
            'user_page_access',
            $userId,
            'Updated page access for: ' . ($user['username'] ?? "user #{$userId}")
        );
    }

    /**
     * Grants every known page to $userId, but ONLY if they currently have
     * zero grant rows -- this guard is what makes it safe to call from both
     * new-user creation and an admin-to-staff demotion without ever
     * clobbering a curated grant set.
     */
    public function ensureDefaultGrants(int $userId): void
    {
        if ($this->userPageAccessModel->getPageKeysForUser($userId) !== []) {
            return;
        }

        $this->userPageAccessModel->grantAllPages($userId, Permissions::allKeys(), null);
    }

    /**
     * Validate a requested permission set, and apply the view-implication rule.
     *
     * Public so controllers can reuse it rather than restating the rules.
     *
     * Holding any action of a module implies holding its `view`. Without this a
     * grant of `students.delete` alone produces a user who may delete records
     * but cannot open the page they live on -- a state the UI would never show
     * and nobody could debug. The UI ticks `view` for you; enforcing it here as
     * well means a hand-crafted POST cannot create that state either.
     *
     * @throws \InvalidArgumentException if any key is not in the catalog
     */
    public function validatePageKeys(array $pageKeys): array
    {
        foreach ($pageKeys as $pageKey) {
            if (! is_string($pageKey) || ! Permissions::isValidKey($pageKey)) {
                throw new \InvalidArgumentException(
                    'Unknown permission: ' . (is_string($pageKey) ? $pageKey : gettype($pageKey))
                );
            }
        }

        $keys = array_values(array_unique($pageKeys));

        foreach ($keys as $key) {
            $module = Permissions::moduleOf($key);

            if ($module !== null && ! in_array($module . '.view', $keys, true)) {
                $keys[] = $module . '.view';
            }
        }

        return array_values($keys);
    }

    /**
     * The catalog grouped for the permission cards, carrying each user's state.
     *
     * One shape used by both the Access Rights screen and the Add/Edit User
     * modals, so the two can never disagree about what exists.
     */
    public function getGroupedPermissions(array $grantedKeys = []): array
    {
        $groups = [];

        foreach (Permissions::MODULES as $module => $definition) {
            $actions = [];

            foreach ($definition['actions'] as $action) {
                $key = $module . '.' . $action;

                $actions[] = [
                    'key'     => $key,
                    'action'  => $action,
                    'label'   => Permissions::ACTION_LABELS[$action],
                    'granted' => in_array($key, $grantedKeys, true),
                ];
            }

            $groups[$module] = [
                'label'       => $definition['label'],
                'group_label' => $definition['group_label'],
                'actions'     => $actions,
            ];
        }

        return $groups;
    }
}
