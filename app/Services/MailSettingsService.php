<?php

namespace App\Services;

/**
 * SMTP configuration, held in `settings` rather than .env so the office can
 * change mail providers without a developer -- the whole point of the
 * Settings console.
 *
 * The SMTP password is stored ENCRYPTED (same base64-wrapped encrypter
 * treatment as BackupPasswordService: Config\Encryption::$rawData is true, so
 * raw ciphertext would break the utf8mb4 settings column under
 * STRICT_TRANS_TABLES).
 */
class MailSettingsService
{
    public const KEY_HOST       = 'mail_smtp_host';
    public const KEY_PORT       = 'mail_smtp_port';
    public const KEY_USER       = 'mail_smtp_user';
    public const KEY_PASSWORD   = 'mail_smtp_password';
    public const KEY_CRYPTO     = 'mail_smtp_crypto';
    public const KEY_FROM_EMAIL = 'mail_from_email';
    public const KEY_FROM_NAME  = 'mail_from_name';
    public const KEY_BATCH_SIZE = 'mail_batch_size';
    public const KEY_BATCH_PAUSE = 'mail_batch_pause';

    private const GROUP = 'mail';

    /** Plain (non-secret) keys, safe to read straight back into the form. */
    private const PLAIN_KEYS = [
        self::KEY_HOST, self::KEY_PORT, self::KEY_USER, self::KEY_CRYPTO,
        self::KEY_FROM_EMAIL, self::KEY_FROM_NAME,
        self::KEY_BATCH_SIZE, self::KEY_BATCH_PAUSE,
    ];

    public const DEFAULT_PORT        = 587;
    public const DEFAULT_CRYPTO      = 'tls';
    public const DEFAULT_BATCH_SIZE  = 25;
    public const DEFAULT_BATCH_PAUSE = 5;

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
    }

    /**
     * Everything the settings form needs. The SMTP password is NEVER
     * returned -- only whether one is set.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $values = $this->settingService->getMany(self::PLAIN_KEYS);

        return [
            self::KEY_HOST        => (string) ($values[self::KEY_HOST] ?? ''),
            self::KEY_PORT        => (int) ($values[self::KEY_PORT] ?: self::DEFAULT_PORT),
            self::KEY_USER        => (string) ($values[self::KEY_USER] ?? ''),
            self::KEY_CRYPTO      => (string) ($values[self::KEY_CRYPTO] ?: self::DEFAULT_CRYPTO),
            self::KEY_FROM_EMAIL  => (string) ($values[self::KEY_FROM_EMAIL] ?? ''),
            self::KEY_FROM_NAME   => (string) ($values[self::KEY_FROM_NAME] ?? ''),
            self::KEY_BATCH_SIZE  => (int) ($values[self::KEY_BATCH_SIZE] ?: self::DEFAULT_BATCH_SIZE),
            self::KEY_BATCH_PAUSE => (int) ($values[self::KEY_BATCH_PAUSE] ?: self::DEFAULT_BATCH_PAUSE),
            'password_configured' => $this->isPasswordConfigured(),
        ];
    }

    public function isPasswordConfigured(): bool
    {
        return (string) ($this->settingService->get(self::KEY_PASSWORD, '') ?? '') !== '';
    }

    /** Whether enough is configured to actually attempt a send. */
    public function isConfigured(): bool
    {
        $all = $this->getAll();

        return $all[self::KEY_HOST] !== '' && $all[self::KEY_FROM_EMAIL] !== '';
    }

    /**
     * @throws \InvalidArgumentException on validation failure
     */
    public function save(array $postData): void
    {
        $host      = trim(sanitize_xss($postData[self::KEY_HOST] ?? ''));
        $port      = (int) ($postData[self::KEY_PORT] ?? self::DEFAULT_PORT);
        $user      = trim(sanitize_xss($postData[self::KEY_USER] ?? ''));
        $crypto    = strtolower(trim((string) ($postData[self::KEY_CRYPTO] ?? self::DEFAULT_CRYPTO)));
        $fromEmail = trim((string) ($postData[self::KEY_FROM_EMAIL] ?? ''));
        $fromName  = trim(sanitize_xss($postData[self::KEY_FROM_NAME] ?? ''));
        $batchSize = (int) ($postData[self::KEY_BATCH_SIZE] ?? self::DEFAULT_BATCH_SIZE);
        $pause     = (int) ($postData[self::KEY_BATCH_PAUSE] ?? self::DEFAULT_BATCH_PAUSE);

        if ($host === '') {
            throw new \InvalidArgumentException('The SMTP server address is required.');
        }
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('The SMTP port must be between 1 and 65535.');
        }
        if (! in_array($crypto, ['tls', 'ssl', 'none'], true)) {
            throw new \InvalidArgumentException('Encryption must be TLS, SSL or None.');
        }
        if ($fromEmail === '' || ! filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid "from" email address is required.');
        }
        if ($batchSize < 1 || $batchSize > 500) {
            throw new \InvalidArgumentException('Batch size must be between 1 and 500.');
        }
        if ($pause < 0 || $pause > 300) {
            throw new \InvalidArgumentException('The pause between batches must be between 0 and 300 seconds.');
        }

        $this->settingService->set(self::KEY_HOST, $host, self::GROUP);
        $this->settingService->set(self::KEY_PORT, (string) $port, self::GROUP);
        $this->settingService->set(self::KEY_USER, $user, self::GROUP);
        $this->settingService->set(self::KEY_CRYPTO, $crypto, self::GROUP);
        $this->settingService->set(self::KEY_FROM_EMAIL, $fromEmail, self::GROUP);
        $this->settingService->set(self::KEY_FROM_NAME, $fromName, self::GROUP);
        $this->settingService->set(self::KEY_BATCH_SIZE, (string) $batchSize, self::GROUP);
        $this->settingService->set(self::KEY_BATCH_PAUSE, (string) $pause, self::GROUP);

        // Blank password field = "leave the stored one alone", so saving other
        // settings does not silently wipe the password.
        //
        // Whitespace is stripped, not just trimmed at the ends. Google
        // displays an App Password as four spaced groups ("abcd efgh ijkl
        // mnop") purely for readability -- the spaces are NOT part of the
        // credential, and pasting them straight in produces a 19-character
        // string that Gmail rejects with exactly the same
        // "535-5.7.8 Username and Password not accepted" as a genuinely wrong
        // password, which makes it very hard to diagnose. This deployment hit
        // precisely that.
        //
        // Safe across providers: no SMTP provider issues a credential whose
        // correctness depends on embedded spaces (SendGrid API keys, AWS SES
        // secrets and Mailgun passwords are all space-free), so removing them
        // can only turn a guaranteed-failing value into the intended one.
        $password = preg_replace('/\s+/u', '', (string) ($postData[self::KEY_PASSWORD] ?? ''));

        if ($password !== '') {
            $this->savePassword($password);
        }

        $this->activityLogService->record('settings.mail.update', 'settings', null, 'Updated SMTP settings');
    }

    /**
     * See BackupPasswordService for why base64 wraps the ciphertext.
     *
     * @throws \RuntimeException when encryption is unavailable
     */
    private function savePassword(string $plain): void
    {
        try {
            $stored = base64_encode(service('encrypter')->encrypt($plain));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Encryption is not configured on this server, so the SMTP password cannot be stored securely. Ask your administrator to run "php spark key:generate".');
        }

        $this->settingService->set(self::KEY_PASSWORD, $stored, self::GROUP);
    }

    /** The decrypted SMTP password, for the mailer only. */
    public function revealPassword(): string
    {
        $stored = (string) ($this->settingService->get(self::KEY_PASSWORD, '') ?? '');

        if ($stored === '') {
            return '';
        }

        try {
            $raw = base64_decode($stored, true);

            return $raw === false ? '' : (string) service('encrypter')->decrypt($raw);
        } catch (\Throwable $e) {
            throw new \RuntimeException('The stored SMTP password could not be decrypted. It may have been saved with a previous encryption key -- please enter it again.');
        }
    }

    /**
     * CI4 Email config array built from the stored settings.
     *
     * @return array<string, mixed>
     */
    public function buildEmailConfig(): array
    {
        $all = $this->getAll();

        return [
            'protocol'   => 'smtp',
            'SMTPHost'   => $all[self::KEY_HOST],
            'SMTPUser'   => $all[self::KEY_USER],
            'SMTPPass'   => $this->revealPassword(),
            'SMTPPort'   => $all[self::KEY_PORT],
            'SMTPCrypto' => $all[self::KEY_CRYPTO] === 'none' ? '' : $all[self::KEY_CRYPTO],
            'SMTPTimeout' => 20,
            'mailType'   => 'html',
            'charset'    => 'UTF-8',
            'wordWrap'   => true,
        ];
    }
}
