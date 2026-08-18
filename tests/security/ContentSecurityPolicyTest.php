<?php

namespace Tests\Security;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;
use Config\ContentSecurityPolicy;
use Config\Security;
use Config\Session;

/**
 * Guards the Content-Security-Policy rollout.
 *
 * The policy is only worth having if two things stay true: the header is
 * actually emitted, and every inline <script> carries a nonce. Miss a nonce
 * and that block silently stops running in the browser -- no PHP error, no
 * failing request, just a dead page. These tests are the cheap way to catch
 * that, because the alternative is noticing in production.
 *
 * @internal
 */
final class ContentSecurityPolicyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /**
     * Renders a page and applies the policy to the response.
     *
     * CodeIgniter builds the CSP headers inside Response::send(), which a
     * feature test never reaches -- it captures the response first. Calling
     * finalize() here runs the identical code the framework runs on a real
     * request, so these assertions reflect what a browser actually receives.
     */
    private function finalizedResponse(string $uri = 'auth/login')
    {
        $result   = $this->get($uri);
        $response = $result->response();
        $response->getCSP()->finalize($response);

        return $response;
    }

    public function testCspIsEnabled(): void
    {
        $this->assertTrue(
            config(App::class)->CSPEnabled,
            'CSPEnabled was turned off -- the policy stops being sent entirely.'
        );
    }

    public function testLoginPageSendsCspHeader(): void
    {
        $this->get('auth/login')->assertOK();

        $this->assertTrue(
            $this->finalizedResponse()->hasHeader('Content-Security-Policy'),
            'No Content-Security-Policy header on a rendered page.'
        );
    }

    /**
     * A nonce is what makes script-src meaningful. Without one in the header,
     * every inline block would be blocked and the UI would be dead.
     */
    public function testScriptSrcCarriesANonce(): void
    {
        $csp = $this->finalizedResponse()->getHeaderLine('Content-Security-Policy');

        $this->assertMatchesRegularExpression(
            "/script-src[^;]*'nonce-[A-Za-z0-9+\/=]+'/",
            $csp,
            "script-src carries no nonce. Header was: {$csp}"
        );
    }

    /**
     * The 74 style="" attributes across the views hang off style-src-attr.
     *
     * This is asserted rather than style-src because style-src is not stable
     * across environments: when CI_DEBUG is on (development and testing, never
     * production) CodeIgniter hands Kint a style nonce, and a nonce makes
     * browsers ignore 'unsafe-inline'. style-src-attr is never nonced --
     * ContentSecurityPolicy::getStyleNonce() only touches styleSrc and
     * styleSrcElem -- so it is what actually keeps those attributes working
     * everywhere. If someone "tidies up" styleSrcAttr, this fails.
     */
    public function testInlineStyleAttributesStayAllowed(): void
    {
        $csp = $this->finalizedResponse()->getHeaderLine('Content-Security-Policy');

        preg_match('/style-src-attr ([^;]*)/', $csp, $m);
        $styleSrcAttr = $m[1] ?? '';

        $this->assertStringContainsString(
            "'unsafe-inline'",
            $styleSrcAttr,
            'style-src-attr lost unsafe-inline -- all 74 inline style attributes stop applying.'
        );
        $this->assertStringNotContainsString('nonce-', $styleSrcAttr);
    }

    /**
     * In production (CI_DEBUG false) no style nonce is generated, so the 10
     * inline <style> blocks rely on 'unsafe-inline' in style-src.
     */
    public function testStyleSrcAllowsInlineForProduction(): void
    {
        $csp = $this->finalizedResponse()->getHeaderLine('Content-Security-Policy');

        preg_match('/style-src ([^;]*)/', $csp, $m);

        $this->assertStringContainsString(
            "'unsafe-inline'",
            $m[1] ?? '',
            'style-src lost unsafe-inline -- the 10 inline <style> blocks stop applying in production.'
        );
    }

    public function testDangerousDirectivesAreLockedDown(): void
    {
        $csp = config(ContentSecurityPolicy::class);

        $this->assertSame('none', $csp->objectSrc);
        $this->assertSame('none', $csp->scriptSrcAttr, 'Inline event handlers must stay blocked.');
        $this->assertSame('self', $csp->baseURI);
        $this->assertSame('self', $csp->frameAncestors);
    }

    /**
     * The placeholder must not survive into the response -- if it does, the
     * literal string {csp-script-nonce} ends up in the HTML and the browser
     * rejects the block.
     */
    public function testNoncePlaceholderIsSubstituted(): void
    {
        $body = (string) $this->finalizedResponse()->getBody();

        $this->assertStringNotContainsString(
            '{csp-script-nonce}',
            $body,
            'Nonce placeholder reached the browser unsubstituted.'
        );
    }

    /**
     * Every inline <script> in every view needs the placeholder. This walks
     * the view tree rather than one rendered page, because most views are
     * only reachable behind a login.
     */
    public function testEveryInlineScriptInEveryViewIsNonced(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(APPPATH . 'Views', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/<script\b([^>]*)>/i', $contents, $matches)) {
                foreach ($matches[1] as $attrs) {
                    if (preg_match('/\bsrc\s*=/i', $attrs)) {
                        continue; // external file, governed by 'self'
                    }

                    if (! str_contains($attrs, 'csp-script-nonce')) {
                        $offenders[] = str_replace(APPPATH, '', $file->getPathname());
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Inline <script> without a nonce -- these blocks will not run:\n" . implode("\n", $offenders)
        );
    }

    public function testInlineEventHandlersAreGoneFromProductionViews(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(APPPATH . 'Views', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // CodeIgniter's own debug error page is only rendered when
            // display_errors is on, i.e. never in production. Out of scope.
            if (str_contains(str_replace('\\', '/', $file->getPathname()), 'Views/errors/')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('/\son(click|change|load|submit|input|focus|blur|keyup|keydown)\s*=\s*"/i', $contents)) {
                $offenders[] = str_replace(APPPATH, '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Inline event handler attributes are blocked by script-src-attr 'none':\n" . implode("\n", $offenders)
        );
    }

    public function testCsrfTokenIsRandomised(): void
    {
        $this->assertTrue(
            config(Security::class)->tokenRandomize,
            'tokenRandomize guards the CSRF token against BREACH while nginx gzips HTML.'
        );
    }

    public function testSessionIsBoundToTheClientIp(): void
    {
        $this->assertTrue(config(Session::class)->matchIP);
    }
}
