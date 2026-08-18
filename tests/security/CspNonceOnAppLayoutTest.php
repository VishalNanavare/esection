<?php

namespace Tests\Security;

use CodeIgniter\HTTP\ContentSecurityPolicy;
use CodeIgniter\Test\CIUnitTestCase;
use Config\ContentSecurityPolicy as CSPConfig;

/**
 * Proves the nonce plumbing itself works.
 *
 * The login page renders layouts/auth, which contains no inline script, so it
 * can never exercise this. An authenticated page would, but reaching one needs
 * a seeded database because AuthFilter re-reads the user on every request --
 * a heavy fixture for what is really a string-substitution question.
 *
 * So this drives CodeIgniter's ContentSecurityPolicy directly with a body
 * shaped like the real layout. Paired with
 * ContentSecurityPolicyTest::testEveryInlineScriptInEveryViewIsNonced (which
 * proves every view carries the placeholder), the two together cover the whole
 * path: placeholder present in the source -> real nonce in the HTML -> same
 * nonce authorised in the header.
 *
 * @internal
 */
final class CspNonceOnAppLayoutTest extends CIUnitTestCase
{
    private function renderWithCsp(string $body): array
    {
        $response = service('response', null, false);
        $response->setBody($body);
        $response->setHeader('Content-Type', 'text/html; charset=UTF-8');

        $csp = new ContentSecurityPolicy(new CSPConfig());
        $csp->finalize($response);

        return [(string) $response->getBody(), $response->getHeaderLine('Content-Security-Policy')];
    }

    public function testPlaceholderBecomesARealNonce(): void
    {
        [$body] = $this->renderWithCsp(
            '<html><body><script {csp-script-nonce}>var a = 1;</script></body></html>'
        );

        $this->assertStringNotContainsString('{csp-script-nonce}', $body, 'Placeholder survived into the HTML.');
        $this->assertMatchesRegularExpression('/<script nonce="[A-Za-z0-9+\/=]+">/', $body);
    }

    public function testTheRenderedNonceIsTheOneAuthorisedInTheHeader(): void
    {
        [$body, $csp] = $this->renderWithCsp(
            '<html><body><script {csp-script-nonce}>var a = 1;</script></body></html>'
        );

        $this->assertSame(1, preg_match('/<script nonce="([^"]+)">/', $body, $m));

        $this->assertStringContainsString(
            "'nonce-{$m[1]}'",
            $csp,
            'The nonce in the HTML is not authorised in script-src; the block would be blocked.'
        );
    }

    /**
     * Every inline block in one response shares a single nonce -- so one
     * placeholder working means all 25 do.
     */
    public function testMultipleInlineBlocksShareOneAuthorisedNonce(): void
    {
        [$body, $csp] = $this->renderWithCsp(
            '<script {csp-script-nonce}>var a;</script>'
            . '<script src="/assets/js/jquery.min.js"></script>'
            . '<script {csp-script-nonce}>var b;</script>'
        );

        preg_match_all('/<script nonce="([^"]+)">/', $body, $m);

        $this->assertCount(2, $m[1], 'Not every placeholder was substituted.');
        $this->assertSame($m[1][0], $m[1][1], 'Blocks got different nonces.');
        $this->assertStringContainsString("'nonce-{$m[1][0]}'", $csp);
    }

    /**
     * External <script src> tags must NOT be given a nonce -- they are
     * governed by 'self', and this confirms nothing rewrites them.
     */
    public function testExternalScriptsAreLeftAlone(): void
    {
        [$body] = $this->renderWithCsp('<script src="/assets/js/jquery.min.js"></script>');

        $this->assertStringContainsString('<script src="/assets/js/jquery.min.js"></script>', $body);
        $this->assertStringNotContainsString('nonce', $body);
    }
}
