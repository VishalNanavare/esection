<?php

namespace Tests\Security;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionMethod;

/**
 * Changing a password must end the account's other sessions.
 *
 * Nothing did this before: a user who believed their account was compromised
 * changed their password and the attacker's session kept working until it
 * expired on its own. That is the one lever the app offers for the situation,
 * and it did not pull anything.
 *
 * Implemented without a schema change. The session records a digest of the
 * bcrypt hash it was established under, and AuthFilter compares it against the
 * stored hash on the revalidation pass it already performs every 60 seconds.
 * A session carrying the old digest is ended.
 *
 * @internal
 */
final class SessionInvalidationTest extends CIUnitTestCase
{
    public function testFingerprintChangesWhenThePasswordChanges(): void
    {
        $before = UserModel::sessionFingerprint(password_hash('OldPass123', PASSWORD_DEFAULT));
        $after  = UserModel::sessionFingerprint(password_hash('NewPass456', PASSWORD_DEFAULT));

        $this->assertNotSame($before, $after, 'a changed password must produce a different fingerprint');
    }

    public function testFingerprintIsStableForTheSameHash(): void
    {
        $hash = password_hash('SamePass', PASSWORD_DEFAULT);

        $this->assertSame(
            UserModel::sessionFingerprint($hash),
            UserModel::sessionFingerprint($hash),
            'an unchanged password must not end the session on every revalidation'
        );
    }

    /**
     * bcrypt salts every call, so two hashes of the SAME password differ --
     * and so must their fingerprints. This is why an administrator re-setting
     * a user to their existing password still ends that user's other sessions,
     * which is the safe direction to err in.
     */
    public function testRehashingTheSamePasswordStillInvalidates(): void
    {
        $a = UserModel::sessionFingerprint(password_hash('Identical', PASSWORD_DEFAULT));
        $b = UserModel::sessionFingerprint(password_hash('Identical', PASSWORD_DEFAULT));

        $this->assertNotSame($a, $b);
    }

    /** The bcrypt verifier itself must never be what sits in the session file. */
    public function testFingerprintDoesNotExposeTheHash(): void
    {
        $hash        = password_hash('Secret123', PASSWORD_DEFAULT);
        $fingerprint = UserModel::sessionFingerprint($hash);

        $this->assertStringNotContainsString($fingerprint, $hash);
        $this->assertStringNotContainsString(substr($hash, 7, 20), $fingerprint);
        $this->assertSame(32, strlen($fingerprint));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $fingerprint);
    }

    /**
     * authenticateUser() strips password_hash before returning -- deliberately,
     * so the verifier never leaves the model. The fingerprint therefore has to
     * be derived inside it; deriving it in the caller would hash an empty
     * string, give every session an identical value, and silently turn the
     * whole check into a no-op.
     */
    public function testAuthenticateUserReturnsAFingerprintAndNotTheHash(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Models/UserModel.php');

        $derive = strpos($source, 'pw_fingerprint\'] = self::sessionFingerprint(');
        $strip  = strpos($source, 'unset($user[\'password_hash\']);');

        $this->assertNotFalse($derive, 'authenticateUser() no longer derives a fingerprint');
        $this->assertNotFalse($strip, 'the password_hash unset has gone -- the hash may now be leaving the model');
        $this->assertLessThan(
            $strip,
            $derive,
            'the fingerprint must be derived BEFORE password_hash is unset, or it hashes an empty string '
            . 'and every session gets an identical value'
        );
    }

    /** The comparison must be constant-time, like every other secret compare here. */
    public function testAuthFilterComparesWithHashEquals(): void
    {
        $source = file_get_contents(APPPATH . 'Filters/AuthFilter.php');

        $this->assertStringContainsString('hash_equals(', (string) $source);
        $this->assertStringContainsString('pw_fingerprint', (string) $source);
    }

    /**
     * Sessions that predate the feature carry no fingerprint. They must be
     * adopted, not ended -- otherwise deploying this signs out everyone who is
     * logged in at the time.
     */
    public function testPreExistingSessionsAreAdoptedRatherThanEnded(): void
    {
        $source = (string) file_get_contents(APPPATH . 'Filters/AuthFilter.php');

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*\$seen\s*===\s*null\s*\)\s*\{\s*\n\s*session\(\)->set\(.pw_fingerprint./',
            $source,
            'a session with no fingerprint must adopt the current one, not be terminated'
        );
    }
}
