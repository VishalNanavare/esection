<?php

namespace App\Controllers;

use App\Models\UserModel;

class Auth extends BaseController
{
    protected UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to(base_url('dashboard'));
        }
        $data = ['title' => 'E-Section Verification Portal'];
        return view('auth/login', $data);
    }

    public function processLogin()
    {
        $username = sanitize_xss($this->request->getPost('username') ?? '');
        $password = $this->request->getPost('password') ?? '';

        if (empty($username) || empty($password)) {
            return redirect()->to(base_url('auth/login'))->with('error', 'Username and password are required.');
        }

        $user = $this->userModel->authenticateUser($username, $password);

        if ($user) {
            $sessionData = [
                'id'         => $user['id'],
                'username'   => $user['username'],
                'full_name'  => $user['full_name'] ?? 'E-Section Staff',
                'role'       => $user['role'] ?? 'staff',
                'isLoggedIn' => true,
            ];
            session()->set($sessionData);
            return redirect()->to(base_url('dashboard'));
        }

        return redirect()->to(base_url('auth/login'))->with('error', 'Invalid login credentials. Please try again.');
    }

    public function logout()
    {
        $session = session();

        // Do NOT use $session->destroy(): in CI 4.7 that is a bare
        // session_destroy(), which leaves the session in PHP_SESSION_NONE.
        // Any setFlashdata() after it -- including the one RedirectResponse
        // ::with() performs -- is then written to a session that will never
        // be persisted, so the message silently disappears.
        //
        // regenerate(true) destroys the old session file, issues a fresh
        // session id (defeating fixation) and re-sends the ci_session cookie,
        // all while keeping the session ACTIVE so flashdata survives.
        $session->remove(['id', 'username', 'full_name', 'role', 'isLoggedIn']);
        $session->regenerate(true);
        $session->setFlashdata('success', 'You have been logged out successfully.');

        return redirect()->to(base_url('auth/login'));
    }
}
