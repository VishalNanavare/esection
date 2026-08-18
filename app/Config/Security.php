<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Security extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * CSRF Protection Method
     * --------------------------------------------------------------------------
     *
     * Protection Method for Cross Site Request Forgery protection.
     *
     * 'session', not 'cookie'. The 'cookie' mode is double-submit: the hash
     * lives only in the csrf_cookie_name cookie, and verify() passes whenever
     * the posted token equals whatever that cookie currently holds. Nothing
     * ties it to ci_session, so the check answers "did the caller know a
     * value they also control?" rather than "was this request issued by a
     * page we served to this user".
     *
     * Demonstrated against this deployment: an admin's ci_session paired with
     * a CSRF token/cookie taken from a separate, never-logged-in visitor was
     * ACCEPTED (303) on POST settings/features/store. In 'session' mode the
     * hash is held server-side in writable/session, which only this app can
     * write, and the same request is rejected 403.
     *
     * Safe for every existing caller: the token field name (csrf_token_name)
     * and header name (X-CSRF-TOKEN) are unchanged, so all 25 csrf_field()
     * views and the one csrf_hash() AJAX header keep working untouched. The
     * only behavioural difference is that a token now expires with the
     * session rather than on its own 7200s cookie clock.
     *
     * Mirrored in .env; kept here too so a lost .env cannot silently revert
     * to the weaker mode.
     *
     * @var string 'cookie' or 'session'
     */
    public string $csrfProtection = 'session';

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Randomization
     * --------------------------------------------------------------------------
     *
     * Randomize the CSRF Token for added security.
     */
    public bool $tokenRandomize = true;

    /**
     * --------------------------------------------------------------------------
     * CSRF Token Name
     * --------------------------------------------------------------------------
     *
     * Token name for Cross Site Request Forgery protection.
     */
    public string $tokenName = 'csrf_test_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Header Name
     * --------------------------------------------------------------------------
     *
     * Header name for Cross Site Request Forgery protection.
     */
    public string $headerName = 'X-CSRF-TOKEN';

    /**
     * --------------------------------------------------------------------------
     * CSRF Cookie Name
     * --------------------------------------------------------------------------
     *
     * Cookie name for Cross Site Request Forgery protection.
     */
    public string $cookieName = 'csrf_cookie_name';

    /**
     * --------------------------------------------------------------------------
     * CSRF Expires
     * --------------------------------------------------------------------------
     *
     * Expiration time for Cross Site Request Forgery protection cookie.
     *
     * Defaults to two hours (in seconds).
     */
    public int $expires = 7200;

    /**
     * --------------------------------------------------------------------------
     * CSRF Regenerate
     * --------------------------------------------------------------------------
     *
     * Regenerate CSRF Token on every submission.
     */
    public bool $regenerate = false;

    /**
     * --------------------------------------------------------------------------
     * CSRF Redirect
     * --------------------------------------------------------------------------
     *
     * Redirect to previous page with error on failure.
     *
     * @see https://codeigniter4.github.io/userguide/libraries/security.html#redirection-on-failure
     */
    public bool $redirect = (ENVIRONMENT === 'production');
}
