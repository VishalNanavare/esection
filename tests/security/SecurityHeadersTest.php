<?php

namespace Tests\Security;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Locks in the response header set and the two runtime php.ini overrides.
 *
 * These are the kind of settings that get quietly dropped during an unrelated
 * refactor and are never noticed, because nothing in the UI changes when they
 * disappear. Asserting them here means a deletion fails the build instead of
 * failing silently in production.
 *
 * @internal
 */
final class SecurityHeadersTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /** @return array<string, string> lower-cased header name => value */
    private function headers(): array
    {
        $response = $this->get('auth/login')->response();

        $out = [];

        foreach (array_keys($response->headers()) as $name) {
            $out[strtolower($name)] = $response->getHeaderLine($name);
        }

        return $out;
    }

    /**
     * @dataProvider expectedHeaders
     */
    public function testHeaderIsSent(string $header, string $expected): void
    {
        $headers = $this->headers();

        $this->assertArrayHasKey($header, $headers, "{$header} is not being sent.");
        $this->assertSame($expected, $headers[$header]);
    }

    public static function expectedHeaders(): array
    {
        return [
            'frame options'   => ['x-frame-options', 'SAMEORIGIN'],
            'nosniff'         => ['x-content-type-options', 'nosniff'],
            'referrer policy' => ['referrer-policy', 'strict-origin-when-cross-origin'],
            'cross-domain'    => ['x-permitted-cross-domain-policies', 'none'],
            'hsts'            => ['strict-transport-security', 'max-age=31536000'],
            // '0' is correct: the legacy XSS auditor this controlled was removed
            // from Chrome and Edge, and 'mode=block' was itself exploitable.
            'legacy xss'      => ['x-xss-protection', '0'],
            'corp'            => ['cross-origin-resource-policy', 'same-origin'],
        ];
    }

    /**
     * Must stay 'same-origin-allow-popups'. Plain 'same-origin' severs
     * window.opener for popups this app opens itself -- the PDF tab in
     * ajax_students_new_js.php and the deferred window.open in
     * ajax_common_js.php both keep the handle they are returned.
     */
    public function testCoopAllowsThisAppsOwnPopups(): void
    {
        $this->assertSame(
            'same-origin-allow-popups',
            $this->headers()['cross-origin-opener-policy'] ?? '',
            'COOP must allow popups; the PDF tabs depend on window.opener.'
        );
    }

    public function testPermissionsPolicyDisablesUnusedCapabilities(): void
    {
        $policy = $this->headers()['permissions-policy'] ?? '';

        foreach (['camera', 'microphone', 'geolocation', 'payment', 'usb', 'serial'] as $feature) {
            $this->assertStringContainsString("{$feature}=()", $policy, "{$feature} is not disabled.");
        }
    }

    /**
     * Naming a feature disables it. fullscreen and clipboard are left out on
     * purpose so they keep their default of 'self' -- listing them would turn
     * them off for the app itself.
     */
    public function testPermissionsPolicyDoesNotDisableFeaturesLeftAtDefault(): void
    {
        $policy = $this->headers()['permissions-policy'] ?? '';

        $this->assertStringNotContainsString('fullscreen=()', $policy);
        $this->assertStringNotContainsString('clipboard-write=()', $policy);
    }

    /**
     * Deliberately unset -- see the reasoning in SecurityHeadersFilter. It buys
     * nothing without SharedArrayBuffer and makes future subresources fail
     * silently. This asserts the decision so it is not added by reflex.
     */
    public function testCoepIsNotSet(): void
    {
        $this->assertArrayNotHasKey(
            'cross-origin-embedder-policy',
            $this->headers(),
            'COEP was added; re-test every asset, PDF and export before keeping it.'
        );
    }

    /**
     * php.ini ships session.use_strict_mode=0, which lets PHP adopt a session
     * id supplied by the client -- the session-fixation precondition. The
     * pre_system event in Config\Events overrides it per request, because the
     * ini file is shared with eleven other vhosts.
     */
    public function testSessionStrictModeIsForcedOn(): void
    {
        $this->assertSame(
            '1',
            ini_get('session.use_strict_mode'),
            'Session fixation protection was lost; check the pre_system hook in Config\Events.'
        );
    }

    /**
     * The execution cap is web-only. spark commands and the backup task are
     * expected to run long and have no proxy timing them out, so capping them
     * would truncate a legitimate backup.
     */
    public function testExecutionTimeCapIsNotAppliedToCli(): void
    {
        $this->assertTrue(is_cli(), 'PHPUnit should be running under CLI.');

        $this->assertSame(
            '0',
            ini_get('max_execution_time'),
            'CLI picked up the web request cap; long backups and imports would be truncated.'
        );
    }
}
