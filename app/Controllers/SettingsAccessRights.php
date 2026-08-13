<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AccessRightsService;

class SettingsAccessRights extends BaseController
{
    protected AccessRightsService $accessRightsService;
    protected UserModel $userModel;

    public function __construct()
    {
        $this->accessRightsService = new AccessRightsService();
        $this->userModel           = new UserModel();
    }

    public function index()
    {
        $users = $this->accessRightsService->getAllUsersWithGrants();

        // The screen opens on the first staff account, so its cards are rendered
        // server-side with that account's grants already applied; picking anyone
        // else swaps them via user().
        $firstGranted = [];

        if ($users !== []) {
            $firstGranted = $this->accessRightsService->getPagesForUser((int) $users[0]['id']);
        }

        return view('settings/access_rights', [
            'title'            => 'Settings — Access Rights',
            'users'            => $users,
            'permissionGroups' => $this->accessRightsService->getGroupedPermissions($firstGranted),
        ]);
    }

    /**
     * The permission cards for one staff account.
     *
     * Returns rendered HTML rather than raw grant data so the swapped-in cards
     * come from the SAME partial the page was built with -- rebuilding 32
     * checkboxes in JavaScript is exactly how the markup and the server's idea
     * of the catalog drift apart.
     */
    public function user($id)
    {
        $user = $this->userModel->find((int) $id);

        if (! $user || ($user['role'] ?? '') !== 'staff') {
            // Administrators hold no grant rows -- they bypass AccessFilter by
            // role -- so there is nothing here to edit for them.
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'That staff account could not be found.',
            ]);
        }

        $granted = $this->accessRightsService->getPagesForUser((int) $id);

        return $this->response->setJSON([
            'status'   => 'success',
            'username' => $user['username'],
            'html'     => view('settings/_permission_groups', [
                'permissionGroups' => $this->accessRightsService->getGroupedPermissions($granted),
                'idPrefix'         => 'rights',
            ]),
        ]);
    }

    /**
     * Save one account's permissions.
     *
     * Deliberately per-user. The previous matrix save iterated EVERY staff user
     * and treated an absent row as "grant nothing", so any screen that showed a
     * subset -- or any payload that lost a row -- silently wiped everyone it did
     * not mention. With 32 permissions the matrix was unusable anyway, and this
     * removes the hazard along with it.
     */
    public function store()
    {
        $userId = (int) ($this->request->getPost('user_id') ?? 0);
        $user   = $userId > 0 ? $this->userModel->find($userId) : null;

        if (! $user || ($user['role'] ?? '') !== 'staff') {
            return $this->respondToPost(
                false,
                'Choose a staff account before saving permissions.',
                base_url('settings/access-rights')
            );
        }

        try {
            $this->accessRightsService->saveGrantsForUser(
                $userId,
                (array) ($this->request->getPost('pages') ?? []),
                (int) session()->get('id')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondToPost(false, $e->getMessage(), base_url('settings/access-rights'));
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAccessRights::store] {message}', ['message' => (string) $e]);

            return $this->respondToPost(
                false,
                'Access rights could not be saved. The issue has been logged.',
                base_url('settings/access-rights'),
                [],
                500
            );
        }

        return $this->respondToPost(
            true,
            'Permissions updated for ' . $user['username'] . '.',
            base_url('settings/access-rights')
        );
    }
}
