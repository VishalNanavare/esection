<?php

namespace App\Controllers;

use App\Services\AccessRightsService;
use App\Services\UserManagementService;

class SettingsUsers extends BaseController
{
    protected UserManagementService $userManagementService;
    protected AccessRightsService $accessRightsService;

    public function __construct()
    {
        $this->userManagementService = new UserManagementService();
        $this->accessRightsService   = new AccessRightsService();
    }

    public function index()
    {
        $data = [
            'title'          => 'Settings — Users',
            'users'          => $this->userManagementService->getAll(),
            'currentUserId'  => (int) session()->get('id'),
            'accessPages'    => $this->accessRightsService->getAllPages(),
        ];

        return view('settings/users', $data);
    }

    public function store()
    {
        try {
            $this->userManagementService->create($this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/users'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsUsers::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/users'))
                ->with('error', 'The user could not be created. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/users'))->with('success', 'User created successfully.');
    }

    public function getJson($id)
    {
        $user = $this->userManagementService->getById((int) $id);
        if ($user) {
            unset($user['password_hash']);
            $user['pages'] = $this->accessRightsService->getPagesForUser((int) $id);
            return $this->response->setJSON(['status' => 'success', 'data' => $user]);
        }
        return $this->response->setJSON(['status' => 'error', 'message' => 'User not found']);
    }

    public function update($id)
    {
        try {
            $this->userManagementService->update((int) $id, $this->request->getPost(), (int) session()->get('id'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/users'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsUsers::update] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/users'))
                ->with('error', 'The user could not be updated. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/users'))->with('success', 'User updated successfully.');
    }

    public function toggleActive($id)
    {
        if (! $this->request->is('post')) {
            return redirect()->to(base_url('settings/users'));
        }

        try {
            $this->userManagementService->toggleActive((int) $id, (int) session()->get('id'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/users'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('settings/users'))->with('success', 'User status updated.');
    }
}
