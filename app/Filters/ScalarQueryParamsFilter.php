<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Drops array-valued query parameters before any controller reads them.
 *
 * Every filter, search and export screen reads its query string as a scalar --
 * `trim((string) ($this->request->getGet('year') ?? ''))` and the like, in 35
 * places. Send `?year[]=x` instead and the (string) cast hits an array, which
 * PHP 8 raises as a warning and CodeIgniter promotes to an uncaught
 * ErrorException: every list, export and Select2 endpoint answers 500 to a
 * request anyone logged in can make by hand.
 *
 * Dropping rather than rejecting, because that is the convention this codebase
 * already chose. Api::colleges() runs `?page=` through filter_var with a
 * default specifically so `?page[]=` "folds back to the default" -- this
 * applies the same rule to every other parameter instead of one, so the
 * controller's own `?? ''` supplies its normal default and the screen renders
 * empty-filtered exactly as if the parameter had been omitted.
 *
 * Nothing legitimate is lost: no view, no script and no controller in this
 * application sends or reads an array-valued GET parameter. Array input that
 * IS meaningful -- `student_ids[]` on the confirmations form -- arrives by
 * POST, which this filter does not touch.
 */
class ScalarQueryParamsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! method_exists($request, 'setGlobal')) {
            return null; // CLI requests have no query string to clean
        }

        $get = $request->getGet();

        if (! is_array($get) || $get === []) {
            return null;
        }

        $scalar = array_filter($get, static fn ($value): bool => ! is_array($value));

        if (count($scalar) === count($get)) {
            return null; // nothing to do, the common case
        }

        $request->setGlobal('get', $scalar);

        // Keep the superglobal in step: helpers and any third-party code read
        // $_GET directly rather than going through the request object.
        $_GET = $scalar;

        log_message('warning', '[ScalarQueryParamsFilter] dropped array query parameter(s) {keys} on {path}', [
            'keys' => implode(',', array_keys(array_diff_key($get, $scalar))),
            'path' => $request->getUri()->getPath(),
        ]);

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do on the way out.
    }
}
