<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Applied on top of authFilter (nested inside its route group, so a caller
 * reaching here is already known to be logged in). Additionally requires the
 * 'admin' role stored in session at login -- see Auth::processLogin().
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('role') === 'admin') {
            return null;
        }

        if ($this->expectsJson($request)) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 403,
                    'message' => 'You do not have permission to access this area.',
                ]);
        }

        return redirect()
            ->to(base_url('dashboard'))
            ->with('error', 'You do not have permission to access Settings.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }

    /**
     * Same detection AuthFilter::expectsJson() uses -- duplicated, not
     * inherited, since AuthFilter's version is private.
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
