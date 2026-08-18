<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AccessRightsService;
use App\Services\ActivityLogService;
use App\Services\LoginThrottleService;
use App\Services\PasswordResetService;
use App\Services\UserManagementService;

class Auth extends BaseController
{
    // LOGIN_ATTEMPTS_PER_MINUTE removed with the cache-backed throttler it
    // configured. The login thresholds now live on LoginThrottleService, next
    // to the code that applies them.

    /** Password-reset requests allowed per IP per minute -- same DoS reasoning as login. */
    private const RESET_REQUESTS_PER_MINUTE = 3;

    // The change-password form verifies the CURRENT password, which makes it a
    // password oracle -- and it was the only such endpoint in the app with no
    // rate limit at all. Someone at an unlocked, already-signed-in desk could
    // guess the account's password as fast as HTTP allows, entirely offline
    // from the login throttle. Ten a minute is far above anyone typing their
    // own password and low enough to make guessing pointless.
    private const PASSWORD_CHANGE_ATTEMPTS_PER_MINUTE = 10;

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

    /**
     * Change your own password, from the account menu in the topbar.
     *
     * Distinct from processResetPassword(): that one proves identity with an
     * emailed token for someone who is locked out, this one proves it with the
     * current password for someone already signed in. Both end at the same
     * policy check so neither can set a password the other would refuse.
     *
     * The route sits behind authFilter, so session()->get('id') is always
     * present by the time this runs.
     */
    public function changePassword()
    {
        $userId = (int) (session()->get('id') ?? 0);

        if ($userId <= 0) {
            return $this->respondToPost(false, 'Your session has expired. Please sign in again.', base_url('auth/login'), [], 401);
        }

        // Keyed on the user id, not the IP: the point is to bound guesses
        // against one account, and every attempt here already belongs to an
        // authenticated session. Same service and window as the reset
        // throttle above, so there is one mechanism to reason about.
        if (service('throttler')->check('pwchange_' . $userId, self::PASSWORD_CHANGE_ATTEMPTS_PER_MINUTE, MINUTE) === false) {
            log_message('warning', '[Auth::changePassword] throttled user {id}', ['id' => (string) $userId]);

            return $this->respondToPost(
                false,
                'Too many password change attempts. Please wait a minute and try again.',
                base_url('dashboard')
            );
        }

        try {
            (new UserManagementService())->changeOwnPassword(
                $userId,
                (string) ($this->request->getPost('current_password') ?? ''),
                (string) ($this->request->getPost('new_password') ?? ''),
                (string) ($this->request->getPost('confirm_password') ?? '')
            );
        } catch (\InvalidArgumentException $e) {
            // Wrong current password, mismatch, or outside the length policy --
            // all of it is text written for the operator.
            return $this->respondToPost(false, $e->getMessage(), base_url('dashboard'));
        } catch (\Throwable $e) {
            // Never let this one carry a trace: the submitted password is a
            // frame argument, and CodeIgniter renders scalar frame arguments
            // verbatim into writable/logs. Same reasoning as processLogin().
            log_message('error', '[Auth::changePassword] {type}: {msg}', [
                'type' => $e::class,
                'msg'  => $e->getMessage(),
            ]);

            return $this->respondToPost(
                false,
                'The password could not be changed. The issue has been logged.',
                base_url('dashboard'),
                [],
                500
            );
        }

        return $this->respondToPost(true, 'Your password has been changed.', base_url('dashboard'));
    }

    public function processLogin()
    {
        $username = sanitize_xss($this->request->getPost('username') ?? '');
        $password = $this->request->getPost('password') ?? '';

        if (empty($username) || empty($password)) {
            return redirect()->to(base_url('auth/login'))->with('error', 'Username and password are required.');
        }

        // Brute-force guard, now backed by the login_attempts table rather than
        // the cache. The cache-backed Throttler worked, but its counters were
        // erased by any `cache:clear` and left nothing to review afterwards.
        //
        // Counted per IP AND per username: an IP-only guard cannot see many
        // machines guessing at one known account. Both windows expire on their
        // own, so no account is ever permanently locked and nobody can lock a
        // colleague out by failing their login on purpose.
        $ip       = $this->request->getIPAddress();
        $throttle = new LoginThrottleService();

        if ($throttle->isBlocked($ip, $username)) {
            // Deliberately NOT recorded.
            //
            // Recording here made the block feed itself: every rejected retry
            // wrote another failure for that username, refreshing the window it
            // was already blocked by. Ten guesses against a known name, then a
            // request every few minutes, and that account stayed locked from
            // EVERY machine indefinitely -- with no operator unlock, because
            // the design deliberately has none. That is a denial of service
            // against a real user, and it is precisely what the comment above
            // claimed the design prevented.
            //
            // A refused request is not a guess. The counter now only ever
            // reflects attempts that actually reached password_verify(), so a
            // block always ages out WINDOW_SECONDS after the last real attempt.
            return redirect()->to(base_url('auth/login'))->with(
                'error',
                'Too many failed sign-in attempts. Please wait about '
                . $throttle->retryAfterMinutes() . ' minutes and try again.'
            );
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

            // Clears the failures that were counting against this sign-in, so
            // someone who mistyped twice before getting it right is not left
            // one attempt away from locking themselves out.
            $throttle->record($ip, $username, true);

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

        $throttle->record($ip, $username, false);

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
