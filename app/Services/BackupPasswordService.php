<?php

namespace App\Services;

/**
 * The password that locks every SQL backup archive, plus the retention count.
 *
 * Follows InstituteDetailsService's shape: a known-keys whitelist over
 * SettingService. Stored ENCRYPTED (CI4's encrypter) rather than in clear,
 * because settings rows are readable by anything with DB access and this one
 * value unlocks a complete copy of the database.
 *
 * Stored once rather than typed per run, so scheduled/unattended backups keep
 * working with no human present -- see BackupService and the spark command.
 */
class BackupPasswordService
{
    private const KEY_PASSWORD  = 'backup_zip_password';
    private const KEY_RETENTION = 'backup_retention_count';
    private const GROUP         = 'backup';

    public const MIN_LENGTH        = 8;
    public const DEFAULT_RETENTION = 10;
    public const MAX_RETENTION     = 100;

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
    }

    /** Whether a backup password has been set at all. */
    public function isConfigured(): bool
    {
        return (string) ($this->settingService->get(self::KEY_PASSWORD, '') ?? '') !== '';
    }

    /**
     * Whether CI4's encrypter can be constructed -- i.e. whether
     * `php spark key:generate` has been run. Drives the setup banner on the
     * Backup screen so a missing key is visible BEFORE the button is clicked
     * rather than as a 500 afterwards.
     */
    public function isEncryptionAvailable(): bool
    {
        try {
            service('encrypter');

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @throws \InvalidArgumentException on validation failure (safe user-facing text)
     * @throws \RuntimeException         when encryption is unavailable or fails its self-check
     */
    public function save(#[\SensitiveParameter] string $plain, #[\SensitiveParameter] string $confirm): void
    {
        $plain   = trim($plain);
        $confirm = trim($confirm);

        if ($plain === '') {
            throw new \InvalidArgumentException('The backup password is required.');
        }
        if (mb_strlen($plain) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException('The backup password must be at least ' . self::MIN_LENGTH . ' characters long.');
        }
        if ($plain !== $confirm) {
            throw new \InvalidArgumentException('The two backup passwords do not match.');
        }

        if (! $this->isEncryptionAvailable()) {
            throw new \RuntimeException('Encryption is not configured on this server, so the backup password cannot be stored securely. Ask your administrator to run "php spark key:generate".');
        }

        $stored = $this->encode($plain);

        // Decrypt round-trip BEFORE persisting. Without this, a misconfigured
        // or half-rotated key is only discovered when someone tries to open a
        // backup weeks later -- by which point every archive written in the
        // meantime is unopenable.
        if ($this->decode($stored) !== $plain) {
            throw new \RuntimeException('The backup password failed its encryption self-check and was not saved. Please try again.');
        }

        $this->settingService->set(self::KEY_PASSWORD, $stored, self::GROUP);

        // Records THAT it changed, never the value itself.
        $this->activityLogService->record(
            'settings.backup.password.update',
            'settings',
            null,
            'Updated the backup file password'
        );
    }

    /**
     * The plaintext password, for BackupService only. Never expose this to a
     * view or a JSON response -- the Settings form is write-only.
     *
     * @throws \RuntimeException when unset or undecryptable
     */
    public function reveal(): string
    {
        $stored = (string) ($this->settingService->get(self::KEY_PASSWORD, '') ?? '');

        if ($stored === '') {
            throw new \RuntimeException('No backup password has been set yet. Set one on the Backup screen before creating a backup.');
        }

        try {
            $plain = $this->decode($stored);
        } catch (\Throwable $e) {
            // Most likely cause: the encryption key in .env was regenerated or
            // rotated after this password was saved. Existing .zip files are
            // unaffected -- their password was baked in at creation time and
            // does not depend on the CI4 key.
            throw new \RuntimeException('The stored backup password could not be decrypted. It may have been saved with a previous encryption key -- please set it again on the Backup screen.');
        }

        if ($plain === '') {
            throw new \RuntimeException('The stored backup password could not be decrypted. Please set it again on the Backup screen.');
        }

        return $plain;
    }

    public function getRetentionCount(): int
    {
        $value = (int) ($this->settingService->get(self::KEY_RETENTION, self::DEFAULT_RETENTION) ?? self::DEFAULT_RETENTION);

        return $value >= 1 && $value <= self::MAX_RETENTION ? $value : self::DEFAULT_RETENTION;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function saveRetentionCount(int $count): void
    {
        if ($count < 1 || $count > self::MAX_RETENTION) {
            throw new \InvalidArgumentException('Keep between 1 and ' . self::MAX_RETENTION . ' backups of each type.');
        }

        $this->settingService->set(self::KEY_RETENTION, (string) $count, self::GROUP);

        $this->activityLogService->record(
            'settings.backup.retention.update',
            'settings',
            null,
            'Set backup retention to ' . $count . ' file(s) per type'
        );
    }

    /**
     * base64 is NOT decoration. Config\Encryption::$rawData is true, so
     * encrypt() returns raw binary, while settings.setting_value is a
     * utf8mb4 TEXT column under STRICT_TRANS_TABLES -- writing the raw bytes
     * throws "Incorrect string value". Verified on this database.
     */
    private function encode(#[\SensitiveParameter] string $plain): string
    {
        return base64_encode(service('encrypter')->encrypt($plain));
    }

    private function decode(string $stored): string
    {
        $raw = base64_decode($stored, true);

        if ($raw === false) {
            throw new \RuntimeException('Stored backup password is not valid base64.');
        }

        return (string) service('encrypter')->decrypt($raw);
    }
}
