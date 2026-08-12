<?php

namespace App\Controllers;

use App\Services\AccessRightsService;

class SettingsAccessRights extends BaseController
{
    protected AccessRightsService $accessRightsService;

    public function __construct()
    {
        $this->accessRightsService = new AccessRightsService();
    }

    public function index()
    {
        $data = [
            'title' => 'Settings — Access Rights',
            'pages' => $this->accessRightsService->getAllPages(),
            'users' => $this->accessRightsService->getAllUsersWithGrants(),
        ];

        return view('settings/access_rights', $data);
    }

    public function store()
    {
        try {
            $this->accessRightsService->saveGrants(
                $this->request->getPost('grants') ?? [],
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

        return $this->respondToPost(true, 'Access rights updated successfully.', base_url('settings/access-rights'));
    }
}
