<?php

namespace Tests\Security;

use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;
use SensitiveParameter;

/**
 * Every parameter that carries a plaintext credential must be marked
 * #[\SensitiveParameter].
 *
 * Why this matters on this host specifically: php.ini leaves
 * zend.exception_ignore_args Off, so PHP writes each *string* frame argument
 * into getTraceAsString() as its literal value, truncated to 15 characters.
 * Anything that logs `(string) $e` above one of these calls therefore writes
 * the credential into writable/logs -- and in full whenever it is 15
 * characters or fewer, which the 8-character minimum makes likely.
 *
 * The attribute is the control rather than careful logging, because it fixes
 * every present AND future logger at once: PHP substitutes
 * Object(SensitiveParameterValue) in the trace, so there is nothing left to
 * leak no matter who catches the exception.
 *
 * @internal
 */
final class SensitiveParameterTest extends CIUnitTestCase
{
    /**
     * class => [method => [parameter names that must be marked]]
     */
    private const REQUIRED = [
        \App\Models\UserModel::class => [
            'authenticateUser' => ['password'],
        ],
        \App\Services\BackupPasswordService::class => [
            'save'   => ['plain', 'confirm'],
            'encode' => ['plain'],
        ],
        \App\Services\BackupService::class => [
            'packageEncryptedZip' => ['password'],
        ],
        \App\Services\MailSettingsService::class => [
            'savePassword' => ['plain'],
        ],
        \App\Services\PasswordResetService::class => [
            'resetWithToken' => ['rawToken', 'newPassword', 'confirmPassword'],
        ],
        \App\Services\UserManagementService::class => [
            'assertPasswordStrength' => ['password'],
            'assertPasswordPolicy'   => ['password'],
            'changeOwnPassword'      => ['current', 'new', 'confirm'],
        ],
    ];

    public static function credentialParameters(): array
    {
        $cases = [];

        foreach (self::REQUIRED as $class => $methods) {
            foreach ($methods as $method => $params) {
                foreach ($params as $param) {
                    $short = substr((string) strrchr($class, '\\'), 1);

                    $cases["{$short}::{$method}(\${$param})"] = [$class, $method, $param];
                }
            }
        }

        return $cases;
    }

    /**
     * @dataProvider credentialParameters
     */
    public function testCredentialParameterIsMarkedSensitive(string $class, string $method, string $param): void
    {
        $reflection = new ReflectionMethod($class, $method);

        $target = null;

        foreach ($reflection->getParameters() as $candidate) {
            if ($candidate->getName() === $param) {
                $target = $candidate;
                break;
            }
        }

        $this->assertNotNull($target, "{$class}::{$method}() has no \${$param} -- was it renamed?");

        $this->assertNotEmpty(
            $target->getAttributes(SensitiveParameter::class),
            "{$class}::{$method}(\${$param}) is not #[\\SensitiveParameter]; its value would be written "
            . 'into any stack trace logged above it.'
        );
    }

    /**
     * Guards the premise. If a future php.ini turns zend.exception_ignore_args
     * On, the attributes become belt-and-braces rather than the only control --
     * but nothing here should depend on that, so this documents the setting
     * this host actually runs rather than asserting a value.
     */
    public function testRedactionActuallyHappensAtRuntime(): void
    {
        $leak = static function (string $secret): void {
            throw new \RuntimeException('boom');
        };

        $safe = static function (#[SensitiveParameter] string $secret): void {
            throw new \RuntimeException('boom');
        };

        $secret = 'CorrectHorseBatteryStaple';

        try {
            $leak($secret);
            $this->fail('expected throw');
        } catch (\Throwable $e) {
            $unprotected = str_contains((string) $e, 'CorrectHorse');
        }

        try {
            $safe($secret);
            $this->fail('expected throw');
        } catch (\Throwable $e) {
            $this->assertStringNotContainsString(
                'CorrectHorse',
                (string) $e,
                'PHP did not redact a #[\\SensitiveParameter] argument; the attributes are not protecting anything.'
            );
        }

        if (! $unprotected) {
            $this->addWarning(
                'zend.exception_ignore_args appears to be On, so unmarked arguments are hidden too. '
                . 'The attributes are still required: the setting is host configuration and can change.'
            );
        }

        $this->assertTrue(true);
    }
}
