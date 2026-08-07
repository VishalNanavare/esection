<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AccessRightsService;
use App\Services\ActivityLogService;
use App\Services\PasswordResetService;

class Auth extends BaseController
{
    /** Failed-or-successful login attempts allowed per IP per minute. */
    private const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    /** Password-reset requests allowed per IP per minute -- same DoS reasoning as login. */
    private const RESET_REQUESTS_PER_MINUTE = 3;

    protected UserModel $userModel;
    protected PasswordResetService $passwordResetService;

    public function __construct()
    {
        $this->userModel            = new UserModel();
        $this->passwordResetService = new PasswordResetService();
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

        // Brute-force guard: 5 attempts per minute per client IP. Without this
        // the login endpoint accepts unlimited password guesses -- bcrypt
        // slows an attacker down but does not stop them.
        //
        // Keyed on IP rather than username on purpose: keying on username lets
        // anyone lock a known account out of the system just by spamming its
        // name (a denial-of-service against the real user). CI4's Throttler
        // uses a token-bucket, so honest users who mistype once or twice are
        // never affected.
        $throttler = service('throttler');

        if ($throttler->check(md5('login_' . $this->request->getIPAddress()), self::LOGIN_ATTEMPTS_PER_MINUTE, MINUTE) === false) {
            return redirect()->to(base_url('auth/login'))
                ->with('error', 'Too many login attempts. Please wait a minute and try again.');
        }

        // Never let a throwable escape this call. CodeIgniter logs every
        // uncaught exception at CRITICAL and renders the stack with
        // render_backtrace(), which formats each scalar frame argument with
        // var_export() -- full length, no masking. authenticateUser() has the
        // submitted password as a frame argument, so any failure inside it
        // (a DB drop mid-query, a bcrypt error) would write that password
        // verbatim into writable/logs. Config\Exceptions::$sensitiveDataInTrace
        // does NOT help here: the framework applies it only in the on-screen
        // debug handler, never on the log path.
        try {
            $user = $this->userModel->authenticateUser($username, $password);
        } catch (\Throwable $e) {
            // Log the class and message only -- deliberately not the trace.
            log_message('error', '[Auth::processLogin] authentication failed: {type}: {msg}', [
                'type' => $e::class,
                'msg'  => $e->getMessage(),
            ]);

            return redirect()->to(base_url('auth/login'))
                ->with('error', 'Login is temporarily unavailable. Please try again in a moment.');
        }

        if ($user) {
            // Regenerate the session ID the moment the session stops being
            // anonymous and starts being authenticated. Without this, a
            // session ID known before login (shared/kiosk browser, or planted
            // via any same-origin XSS) keeps working afterwards as the
            // logged-in user -- classic session fixation. Same regenerate(true)
            // logout() already uses; it swaps the id without clearing the
            // session data being set just below.
            session()->regenerate(true);

            $sessionData = [
                'id'         => $user['id'],
                'username'   => $user['username'],
                'full_name'  => $user['full_name'] ?? 'E-Section Staff',
                'role'       => $user['role'] ?? 'staff',
                'isLoggedIn' => true,
            ];

            // Cached once at login so every AccessFilter check is a plain
            // in-session array lookup, not a DB hit per request. Admin gets
            // [] deliberately -- admin bypasses AccessFilter's role check
            // before this key is ever read, so populating it would be dead
            // data (Access Rights only ever governs staff accounts).
            $sessionData['page_access'] = ($sessionData['role'] === 'admin')
                ? []
                : (new AccessRightsService())->getPagesForUser((int) $user['id']);

            // Login itself just read the DB, so start the periodic
            // re-validation window from here (see AuthFilter) instead of
            // immediately re-checking on the very next request.
            $sessionData['auth_revalidated_at'] = time();

            session()->set($sessionData);

            // Recorded AFTER the session is set, so the entry carries the
            // real user_id/username rather than nulls. The audit trail had no
            // authentication events at all: it could show what a user changed
            // but never that they signed in, which is the first thing anyone
            // asks when reviewing an incident.
            (new ActivityLogService())->record(
                'auth.login',
                'user',
                (int) $user['id'],
                'Signed in from ' . $this->request->getIPAddress()
            );

            return redirect()->to(base_url('dashboard'));
        }

        // Failed attempts matter more than successful ones for spotting a
        // brute-force run. There is no session here, so record() stores null
        // for user_id/username -- the attempted username goes in the
        // description instead. The password is deliberately never touched.
        (new ActivityLogService())->record(
            'auth.login_failed',
            'user',
            null,
            'Failed sign-in for "' . $username . '" from ' . $this->request->getIPAddress()
        );

        return redirect()->to(base_url('auth/login'))->with('error', 'Invalid login credentials. Please try again.');
    }

    public function forgotPassword()
    {
        return view('auth/forgot_password', ['title' => 'Forgot Password']);
    }

    public function processForgotPassword()
    {
        $throttler = service('throttler');
        if ($throttler->check(md5('reset_' . $this->request->getIPAddress()), self::RESET_REQUESTS_PER_MINUTE, MINUTE) === false) {
            return redirect()->to(base_url('auth/forgotPassword'))
                ->with('error', 'Too many reset requests. Please wait a minute and try again.');
        }

        $identifier = sanitize_xss($this->request->getPost('identifier') ?? '');

        try {
            $this->passwordResetService->request($identifier);
        } catch (\Throwable $e) {
            // Never let an internal failure surface a different message than
            // the success path -- that difference is itself an oracle for
            // which accounts exist. Log it, show the same generic text.
            log_message('error', '[Auth::processForgotPassword] {message}', ['message' => (string) $e]);
        }

        // Identical outcome whether or not the account exists -- see
        // PasswordResetService's own docblock for why.
        return redirect()->to(base_url('auth/login'))
            ->with('success', 'If an account matches that username or email, a reset link has been sent to the address on file.');
    }

    public function resetPassword($token = '')
    {
        $token = (string) $token;

        if (! $this->passwordResetService->tokenIsValid($token)) {
            return redirect()->to(base_url('auth/forgotPassword'))
                ->with('error', 'This reset link is invalid or has expired. Request a new one below.');
        }

        return view('auth/reset_password', ['title' => 'Reset Password', 'token' => $token]);
    }

    public function processResetPassword()
    {
        $token   = (string) ($this->request->getPost('token') ?? '');
        $newPass = (string) ($this->request->getPost('password') ?? '');
        $confirm = (string) ($this->request->getPost('password_confirm') ?? '');

        try {
            $this->passwordResetService->resetWithToken($token, $newPass, $confirm);
        } catch (\InvalidArgumentException $e) {
            // Expected validation outcome -- its message is written for the user.
            return redirect()->to(base_url('auth/resetPassword/' . $token))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            // Anything else must not escape: resetWithToken() carries the new
            // password AND the reset token as frame arguments, and CodeIgniter's
            // uncaught-exception logger var_export()s every frame argument in
            // full into writable/logs. Same reasoning as processLogin() above.
            log_message('error', '[Auth::processResetPassword] reset failed: {type}: {msg}', [
                'type' => $e::class,
                'msg'  => $e->getMessage(),
            ]);

            return redirect()->to(base_url('auth/resetPassword/' . $token))
                ->with('error', 'The password could not be reset right now. Please request a new link and try again.');
        }

        return redirect()->to(base_url('auth/login'))
            ->with('success', 'Your password has been reset. You can now log in.');
    }

    public function logout()
    {
        $session = session();

        // Logout is a GET (the sidebar link and its SweetAlert confirm depend
        // on that, so it stays a GET), which means it never reaches the csrf
        // filter -- Config\Filters::$methods covers POST/PUT/PATCH/DELETE
        // only. A stranger's page could therefore log a user out mid-batch
        // with a top-level navigation or an <img src>, since SameSite=Lax
        // still sends the cookie on a real navigation.
        //
        // Rather than change the route (a UX change), require the request to
        // have come from this app. An absent Referer is allowed on purpose:
        // browsers omit it in legitimate privacy configurations, and refusing
        // those would break logout for real users. That still blocks the
        // cross-site case, which always carries the attacker's own origin.
        $referer = (string) $this->request->getServer('HTTP_REFERER');

        if ($referer !== '' && ! str_starts_with($referer, base_url())) {
            return redirect()->to(base_url('dashboard'));
        }

        // Recorded BEFORE the session keys are removed, so the entry still
        // carries who was signing out. Pairs with auth.login to give the
        // trail a complete session lifecycle.
        (new ActivityLogService())->record(
            'auth.logout',
            'user',
            (int) ($session->get('id') ?? 0) ?: null,
            'Signed out'
        );

        // Do NOT use $session->destroy(): in CI 4.7 that is a bare
        // session_destroy(), which leaves the session in PHP_SESSION_NONE.
        // Any setFlashdata() after it -- including the one RedirectResponse
        // ::with() performs -- is then written to a session that will never
        // be persisted, so the message silently disappears.
        //
        // regenerate(true) destroys the old session file, issues a fresh
        // session id (defeating fixation) and re-sends the ci_session cookie,
        // all while keeping the session ACTIVE so flashdata survives.
        $session->remove(['id', 'username', 'full_name', 'role', 'isLoggedIn', 'page_access', 'auth_revalidated_at']);
        $session->regenerate(true);
        $session->setFlashdata('success', 'You have been logged out successfully.');

        return redirect()->to(base_url('auth/login'));
    }
}
