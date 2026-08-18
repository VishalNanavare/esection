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
    use DetectsJsonRequests;

    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('role') === 'admin') {
            return null;
        }

        // Accepts one key ('accessFilter:universities') or several
        // ('accessFilter:students_new,universities'), in which case ANY of
        // them grants access. CI4 already splits a comma-separated filter
        // argument into $arguments for us, so this needs no framework change
        // and every existing single-key call site behaves exactly as before.
        //
        // The multi-key form exists for the shared Select2 endpoints under
        // /api/*: they feed pickers on several different pages, so no single
        // page_key describes them. Requiring "any page that actually uses
        // this dropdown" is the honest rule -- previously they carried
        // authFilter alone, which meant the Access Rights system had no
        // effect on them at all and the full university directory was
        // enumerable by any logged-in account.
        $pageKeys = array_values(array_filter(
            (array) ($arguments ?? []),
            static fn ($key): bool => is_string($key) && $key !== ''
        ));

        if ($pageKeys === []) {
            log_message('error', '[AccessFilter] Route guarded with accessFilter but no page_key argument was supplied.');

            return $this->denyResponse($request);
        }

        $granted = session()->get('page_access');

        // is_array() alone was not enough. A session established BEFORE the
        // granular-permissions migration holds the six legacy page keys
        // ('students_new', 'universities', ...) -- a perfectly good array, so
        // the fallback never fired, and every intersect against a
        // 'module.action' key came back empty. Those users were denied
        // everything, on every page, until AuthFilter's 60-second refresh
        // happened to come round. Detect the legacy shape and re-read now.
        $isStale = ! is_array($granted)
            || array_intersect($granted, array_keys(\Config\Permissions::LEGACY_KEY_TO_MODULE)) !== [];

        if ($isStale) {
            $granted = (new AccessRightsService())->getPagesForUser((int) session()->get('id'));

            // Write it back, or every request in the next 60 seconds repeats
            // this query.
            session()->set('page_access', $granted);
        }

        if (array_intersect($pageKeys, $granted) !== []) {
            return null;
        }

        // Nothing recorded a refusal anywhere before this, so an account
        // probing pages it does not hold left no trace at all -- neither in
        // the activity log nor on disk. Written at 'warning' because the
        // production threshold (Config\Logger::$threshold = 5) keeps warnings
        // and discards 'info'.
        //
        // log_message rather than ActivityLogService::record(): a misbehaving
        // client retrying a forbidden endpoint would otherwise write one
        // database row per request.
        log_message('warning', '[AccessFilter] denied user {id} ({user}) -> {path}; needed one of: {needed}', [
            'id'     => (string) (session()->get('id') ?? '-'),
            'user'   => (string) (session()->get('username') ?? '-'),
            'path'   => $request->getUri()->getPath(),
            'needed' => implode(',', $pageKeys),
        ]);

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
}
