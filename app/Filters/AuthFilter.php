<?php

namespace App\Filters;

use App\Models\UserModel;
use App\Services\AccessRightsService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    use DetectsJsonRequests;

    /**
     * How stale session('role')/session('page_access') are allowed to get
     * before being re-checked against the database.
     *
     * Without this, role/page_access/is_active are cached into session ONCE
     * at login (Auth::processLogin()) and every later filter -- including
     * this one -- reads only that cached copy. Deactivating a user,
     * demoting an admin, or revoking a staff page-grant therefore had zero
     * effect on an already-active session for as long as it stayed alive
     * (up to Config\Session::$expiration, 7200s / 2 hours).
     *
     * Re-checking on literally every request would work too, but costs one
     * DB read per request for every logged-in user. A short bound instead
     * caps the worst case at this many seconds of staleness -- still a
     * ~120x tighter window than before -- for a small internal tool's
     * traffic, one query per active user per interval is negligible.
     */
    private const REVALIDATE_INTERVAL_SECONDS = 60;

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! session()->get('isLoggedIn')) {
            if ($this->expectsJson($request)) {
                return $this->jsonUnauthorised();
            }

            return redirect()
                ->to(base_url('auth/login'))
                ->with('error', 'Please login to access E-Section portal.');
        }

        $idleFailure = $this->endIfIdle($request);
        if ($idleFailure !== null) {
            return $idleFailure;
        }

        $revalidationFailure = $this->revalidateIfStale($request);
        if ($revalidationFailure !== null) {
            return $revalidationFailure;
        }

        return null;
    }

    /**
     * Enforces Config\Session::$expiration server-side.
     *
     * CodeIgniter passes that value to session.gc_maxlifetime and to the
     * cookie's expiry, and neither actually ends anything on its own: PHP's
     * garbage collector runs on a probability, so a session file can easily
     * outlive its window, and the cookie's lifetime is a hint to the browser
     * that a client is free to ignore. So the declared two-hour limit was
     * documentation rather than a rule -- an unattended session could keep
     * working long past it.
     *
     * Idle time, not total age: the clock resets on every request, so this
     * ends abandoned sessions and never interrupts anyone still working.
     *
     * A session with no last_seen predates this check and adopts the current
     * time rather than being ended, so deploying it does not sign out
     * everyone who is logged in at the time.
     */
    private function endIfIdle(RequestInterface $request)
    {
        $limit = (int) config(\Config\Session::class)->expiration;

        if ($limit <= 0) {
            return null; // configured never to expire
        }

        $lastSeen = session()->get('last_seen');
        $now      = time();

        if ($lastSeen !== null && ($now - (int) $lastSeen) > $limit) {
            log_message('info', '[AuthFilter] session ended after {mins} minutes idle for user {id}', [
                'mins' => (string) (int) (($now - (int) $lastSeen) / 60),
                'id'   => (string) (session()->get('id') ?? '-'),
            ]);

            session()->remove(['id', 'username', 'full_name', 'role', 'isLoggedIn', 'page_access', 'auth_revalidated_at', 'pw_fingerprint', 'last_seen']);
            session()->regenerate(true);

            if ($this->expectsJson($request)) {
                return $this->jsonUnauthorised('Your session has timed out. Please sign in again.');
            }

            return redirect()
                ->to(base_url('auth/login'))
                ->with('error', 'Your session timed out after a period of inactivity. Please sign in again.');
        }

        session()->set('last_seen', $now);

        return null;
    }

    /**
     * Re-reads the account from the database once the cached copy is older
     * than REVALIDATE_INTERVAL_SECONDS, refreshing role/page_access or
     * ending the session outright if the account was deactivated or
     * deleted. Returns null when nothing needs to happen (still fresh, or
     * just refreshed successfully).
     */
    private function revalidateIfStale(RequestInterface $request)
    {
        $lastCheck = (int) (session()->get('auth_revalidated_at') ?? 0);

        if (time() - $lastCheck < self::REVALIDATE_INTERVAL_SECONDS) {
            return null;
        }

        $id   = session()->get('id');
        $user = $id !== null ? (new UserModel())->find((int) $id) : null;

        if ($user === null || (int) ($user['is_active'] ?? 0) === 0) {
            session()->remove(['id', 'username', 'full_name', 'role', 'isLoggedIn', 'page_access', 'auth_revalidated_at']);
            session()->regenerate(true);

            if ($this->expectsJson($request)) {
                return $this->jsonUnauthorised('Your account is no longer active. Please contact an administrator.');
            }

            return redirect()
                ->to(base_url('auth/login'))
                ->with('error', 'Your session has ended because your account is no longer active.');
        }

        // Has the password changed under this session's feet?
        //
        // Nothing used to end a session when its account's password changed,
        // so the one action a user takes when they think they are compromised
        // left the attacker's session running until it expired on its own.
        //
        // A session with no fingerprint at all predates this check. Those are
        // adopted rather than ended, so deploying this does not sign every
        // logged-in user out; from their next request on they are covered like
        // everyone else.
        $fingerprint = UserModel::sessionFingerprint((string) ($user['password_hash'] ?? ''));
        $seen        = session()->get('pw_fingerprint');

        if ($seen === null) {
            session()->set('pw_fingerprint', $fingerprint);
        } elseif (! hash_equals($fingerprint, (string) $seen)) {
            log_message('warning', '[AuthFilter] session ended for user {id}: password changed elsewhere', [
                'id' => (string) ($user['id'] ?? '-'),
            ]);

            session()->remove(['id', 'username', 'full_name', 'role', 'isLoggedIn', 'page_access', 'auth_revalidated_at', 'pw_fingerprint']);
            session()->regenerate(true);

            if ($this->expectsJson($request)) {
                return $this->jsonUnauthorised('Your password was changed. Please sign in again.');
            }

            return redirect()
                ->to(base_url('auth/login'))
                ->with('error', 'Your password was changed, so this session has ended. Please sign in again.');
        }

        // Refresh the two values every other filter trusts from session,
        // exactly as Auth::processLogin() computes them the first time.
        $role = $user['role'] ?? 'staff';

        session()->set([
            'role'               => $role,
            'full_name'          => $user['full_name'] ?? session()->get('full_name'),
            'page_access'        => $role === 'admin' ? [] : (new AccessRightsService())->getPagesForUser((int) $user['id']),
            'auth_revalidated_at' => time(),
        ]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }

    /**
     * Would a redirect be useless (or actively harmful) to this caller?
     *
     * A 302 to an HTML login page handed to a jQuery caller running
     * dataType:'json' produces a `parsererror`, not a usable signal. The
     * caller needs a status code it can branch on.
     *
     * Note that PDF routes are opened via window.open -- a real navigation,
     * not XHR -- so they are correctly excluded here and keep redirecting.
     */

    /**
     * 401 with an envelope that is ALSO a valid empty Select2 payload.
     *
     * A client that forgets to inspect xhr.status therefore degrades to
     * "no options available" instead of throwing.
     */
    private function jsonUnauthorised(string $message = 'Your session has expired. Please log in again.'): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON([
                'status'     => 'error',
                'code'       => 401,
                'message'    => $message,
                'login_url'  => base_url('auth/login'),
                'results'    => [],
                'pagination' => ['more' => false],
            ]);
    }
}
