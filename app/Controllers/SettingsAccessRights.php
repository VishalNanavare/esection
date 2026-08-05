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
            return $this->failResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            log_message('error', '[SettingsAccessRights::store] {message}', ['message' => (string) $e]);

            return $this->failResponse('Access rights could not be saved. The issue has been logged.', 500);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Access rights updated successfully.',
            ]);
        }

        return redirect()->to(base_url('settings/access-rights'))->with('success', 'Access rights updated successfully.');
    }

    private function failResponse(string $message, int $statusCode)
    {
        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode($statusCode)->setJSON(['status' => 'error', 'message' => $message]);
        }

        return redirect()->to(base_url('settings/access-rights'))->with('error', $message);
    }
}
