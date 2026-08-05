<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Global pre-request security check
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-XSS-Protection', '1; mode=block');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('X-Permitted-Cross-Domain-Policies', 'none');

        // The app is served over HTTPS only (the vhost has no port-80
        // listener), but nothing previously TOLD the browser that -- so a
        // first request typed as http:// could still be intercepted before
        // the redirect. HSTS pins the browser to HTTPS for a year after its
        // first visit.
        //
        // Set here rather than via App::$forceGlobalSecureRequests because
        // that flag also makes CodeIgniter itself issue redirects, which is a
        // behaviour change; this is header-only. No `preload`/`includeSubDomains`
        // deliberately: preload is effectively irreversible, and subdomains on
        // this host are separate projects that are not all HTTPS-only.
        $response->setHeader('Strict-Transport-Security', 'max-age=31536000');

        return $response;
    }
}
