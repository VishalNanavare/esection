<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Applied here as well as in after(), because CodeIgniter's exception
        // handler renders 404/500 pages WITHOUT running the after-filter
        // chain -- measured: a 404 came back with no X-Frame-Options, no
        // nosniff and no HSTS at all, while a 200 had the full set. Setting
        // them on the shared response service this early means the error
        // path inherits them too.
        $this->apply(service('response'));

        // PHP advertises its exact build ("X-Powered-By: PHP/8.5.1") on every
        // response, which hands an attacker the precise version to look up
        // CVEs against for free. The canonical fix is expose_php=Off in
        // php.ini, but that file is shared with eleven other vhosts on this
        // box, so it is removed here instead -- same result for this app,
        // no server-wide change.
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $this->apply($response);

        return $response;
    }

    /**
     * The header set itself, in one place so before() and after() cannot
     * drift apart.
     */
    private function apply(ResponseInterface $response): void
    {
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // '0', not '1; mode=block'. The legacy XSS Auditor this header
        // controlled has been REMOVED from Chrome and Edge and was never in
        // Firefox, so '1' does nothing on any current browser -- and on the
        // versions that did honour it, 'mode=block' introduced its own
        // vulnerability (an attacker could selectively disable scripts on a
        // page to alter its behaviour). Both Google and the OWASP Secure
        // Headers project now recommend sending '0' explicitly. Real XSS
        // defence here is the esc()/esEscapeHtml() output encoding.
        $response->setHeader('X-XSS-Protection', '0');

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

        // Switches off browser features this app never uses, so a successful
        // injection cannot reach for a camera, a microphone or a location
        // prompt on a logged-in operator's machine. Every item listed is
        // verified unused in app/Views and public/assets/js.
        //
        // fullscreen and clipboard are deliberately NOT listed: leaving a
        // feature out keeps its default (self), while naming it with () would
        // disable it outright. Nothing here should be able to break a page by
        // being absent.
        $response->setHeader(
            'Permissions-Policy',
            'accelerometer=(), autoplay=(), browsing-topics=(), camera=(), '
            . 'display-capture=(), encrypted-media=(), geolocation=(), '
            . 'gyroscope=(), idle-detection=(), magnetometer=(), microphone=(), '
            . 'midi=(), payment=(), picture-in-picture=(), '
            . 'publickey-credentials-get=(), screen-wake-lock=(), serial=(), '
            . 'usb=(), xr-spatial-tracking=()'
        );

        // 'same-origin-allow-popups', NOT 'same-origin'. The stricter value
        // severs window.opener for popups this app opens itself, and it opens
        // several: the PDF tab in ajax_students_new_js.php and the deferred
        // window.open('', '_blank') in ajax_common_js.php both rely on holding
        // the handle they get back. allow-popups keeps that working while
        // still stopping a cross-origin document from getting a reference to
        // this window, which is what the XS-Leaks and tabnabbing classes need.
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // Nothing outside this origin has any business embedding E-Section's
        // pages, PDFs or spreadsheets. This is the server-side half of the
        // clickjacking/leak protection that frame-ancestors covers for frames.
        $response->setHeader('Cross-Origin-Resource-Policy', 'same-origin');

        // Cross-Origin-Embedder-Policy is deliberately NOT set.
        //
        // Its purpose is to unlock cross-origin isolation -- SharedArrayBuffer
        // and high-resolution timers. This app uses neither, so require-corp
        // would buy nothing, while making every future subresource fail
        // silently unless it carries CORP or CORS. That is a foot-gun with no
        // upside here. If cross-origin isolation is ever actually needed:
        //     $response->setHeader('Cross-Origin-Embedder-Policy', 'require-corp');
        // and then re-test every asset, PDF and export.
    }
}
