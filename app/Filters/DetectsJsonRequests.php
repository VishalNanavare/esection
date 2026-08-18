<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Decides whether a caller wants JSON back rather than a redirect.
 *
 * This lived byte-for-byte identically in AccessFilter, AuthFilter and
 * AdminFilter. The three are the app's only authorization boundary, so a rule
 * that drifted in one of them -- a new AJAX prefix added to two filters and
 * missed in the third -- would send an HTML redirect to a fetch() caller and
 * surface as "the page did nothing" rather than as an error. One copy means
 * that cannot happen.
 *
 * A trait rather than a helper function because filters run before the
 * controller, so the helper autoload BaseController performs has not happened
 * yet and each filter would have to load it by hand.
 */
trait DetectsJsonRequests
{
    private function expectsJson(RequestInterface $request): bool
    {
        $path = '/' . ltrim($request->getUri()->getPath(), '/');

        if (str_starts_with($path, '/api/')) {
            return true;
        }

        if (method_exists($request, 'isAJAX') && $request->isAJAX()) {
            return true;
        }

        return str_contains((string) $request->getHeaderLine('Accept'), 'application/json');
    }
}
