<?php

namespace App\Filters;

use App\Services\AccessRightsService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Page-level gate for the 6 operational feature areas managed from
 * Settings > Access Rights. Takes which page it is guarding as
 * $arguments[0] via the filter's colon-argument, e.g.
 * ['filter' => 'accessFilter:universities'] -- one Filter class, six call
 * sites in Routes.php.
 *
 * Admin bypasses unconditionally, exactly like AdminFilter -- admin never
 * consults user_page_access.
 *
 * The normal path reads session('page_access'), populated once at login by
 * Auth::processLogin(). The DB read below is a defensive fallback only, for
 * a session that predates this feature or was otherwise corrupted -- it
 * should not be the common path in production.
 */
class AccessFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('role') === 'admin') {
            return null;
        }

        $pageKey = $arguments[0] ?? null;

        if ($pageKey === null) {
            log_message('error', '[AccessFilter] Route guarded with accessFilter but no page_key argument was supplied.');

            return $this->denyResponse($request);
        }

        $granted = session()->get('page_access');

        if (! is_array($granted)) {
            $granted = (new AccessRightsService())->getPagesForUser((int) session()->get('id'));
        }

        if (in_array($pageKey, $granted, true)) {
            return null;
        }

        return $this->denyResponse($request);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }

    private function denyResponse(RequestInterface $request)
    {
        if ($this->expectsJson($request)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 403,
                    'message' => 'You do not have permission to access this page.',
                ]);
        }

        return redirect()
            ->to(base_url('dashboard'))
            ->with('error', 'You do not have permission to access this page.');
    }

    /**
     * Same detection AdminFilter::expectsJson() uses -- duplicated, not
     * inherited, since AuthFilter's own version is private.
     */
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
