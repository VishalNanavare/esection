<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'authFilter'    => \App\Filters\AuthFilter::class,
        'adminFilter'   => \App\Filters\AdminFilter::class,
        'accessFilter'  => \App\Filters\AccessFilter::class,
        'securityHeaders' => \App\Filters\SecurityHeadersFilter::class,
    ];

    public array $required = [
        'before' => [
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'securityHeaders',
        ],
    ];

    public array $globals = [
        'before' => [],
        'after'  => [
            'securityHeaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    /**
     * CSRF is enforced per HTTP method rather than via $globals['before'] so
     * GET/HEAD navigation is untouched and only state-changing requests pay
     * the check. Safe here because Config\Routing::$autoRoute is false, so a
     * controller cannot be reached with an unexpected verb.
     *
     * Every POST route has a token source: the forms all carry csrf_field(),
     * and students/storeBatch sends the X-CSRF-TOKEN header (Security::
     * getPostedToken() checks POST body, then header, then JSON body).
     *
     * Deliberately NOT stating a route count here -- an earlier version of
     * this comment claimed "10 POST routes" and was silently wrong by 3x once
     * the Settings, history and backup routes landed. A number that has to be
     * hand-maintained to stay true is worse than no number.
     *
     * Rollback is one line: set this back to [].
     */
    public array $methods = [
        'POST'   => ['csrf'],
        'PUT'    => ['csrf'],
        'PATCH'  => ['csrf'],
        'DELETE' => ['csrf'],
    ];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
