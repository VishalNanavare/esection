<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('isLoggedIn')) {
            return null;
        }

        if ($this->expectsJson($request)) {
            return $this->jsonUnauthorised();
        }

        return redirect()
            ->to(base_url('auth/login'))
            ->with('error', 'Please login to access E-Section portal.');
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

    /**
     * 401 with an envelope that is ALSO a valid empty Select2 payload.
     *
     * A client that forgets to inspect xhr.status therefore degrades to
     * "no options available" instead of throwing.
     */
    private function jsonUnauthorised(): ResponseInterface
    {
        return service('response')
            ->setStatusCode(401)
            ->setJSON([
                'status'     => 'error',
                'code'       => 401,
                'message'    => 'Your session has expired. Please log in again.',
                'login_url'  => base_url('auth/login'),
                'results'    => [],
                'pagination' => ['more' => false],
            ]);
    }
}
