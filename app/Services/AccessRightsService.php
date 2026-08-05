<?php

namespace App\Services;

use App\Models\AccessPageModel;
use App\Models\UserModel;
use App\Models\UserPageAccessModel;

/**
 * Owns the page-level Access Rights system introduced 2026-07-30. Only ever
 * governs `staff` accounts -- `role === 'admin'` bypasses every check
 * unconditionally (see AccessFilter) and never has rows here.
 */
class AccessRightsService
{
    /** Mirrors 2026-07-30-000001_CreateAccessRightsTables.php's seeded PAGES list. */
    public const PAGE_DEFINITIONS = [
        'students_new'         => 'Students - New Entry',
        'universities'         => 'Universities',
        'confirmations'        => 'Confirmations',
        'regularization'       => 'Regularization',
        'reminders_university' => 'Reminders - University',
        'reminders_student'    => 'Reminders - Student',
    ];

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
        $pageKeys = array_keys(self::PAGE_DEFINITIONS);

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
     * Bulk matrix save ONLY. Iterates every staff user (not just the keys
     * present in $grants), since a fully-unchecked row is absent from POST
     * entirely -- same "absent means grant nothing" contract as
     * FeatureToggleService::save(). Deliberately NOT reused for a
     * single-user save (see saveGrantsForUser): applying this same
     * "iterate everyone" rule to a single-user payload would silently wipe
     * every OTHER staff user's grants down to zero.
     *
     * @param array<int, array<int, string>> $grants [user_id => [page_key, ...]]
     * @throws \InvalidArgumentException on an unknown page_key
     */
    public function saveGrants(array $grants, ?int $actingUserId): void
    {
        $staff   = $this->userModel->getAllStaffOrdered();
        $changed = [];

        foreach ($staff as $user) {
            $userId       = (int) $user['id'];
            $requested    = $this->validatePageKeys($grants[$userId] ?? []);
            $before       = $this->userPageAccessModel->getPageKeysForUser($userId);

            sort($before);
            $sortedRequested = $requested;
            sort($sortedRequested);

            if ($before === $sortedRequested) {
                continue;
            }

            $this->userPageAccessModel->replaceGrantsForUser($userId, $requested, $actingUserId);
            $changed[] = $user['username'];
        }

        if ($changed !== []) {
            $this->activityLogService->record(
                'access_rights.update',
                'user_page_access',
                null,
                'Updated page access for: ' . implode(', ', $changed)
            );
        }
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

        $this->userPageAccessModel->grantAllPages($userId, array_keys(self::PAGE_DEFINITIONS), null);
    }

    /**
     * @throws \InvalidArgumentException if any key isn't one of the known pages
     */
    private function validatePageKeys(array $pageKeys): array
    {
        $known = array_keys(self::PAGE_DEFINITIONS);

        foreach ($pageKeys as $pageKey) {
            if (! in_array($pageKey, $known, true)) {
                throw new \InvalidArgumentException('Unknown page: ' . $pageKey);
            }
        }

        return array_values(array_unique($pageKeys));
    }
}
