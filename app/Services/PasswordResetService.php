<?php

namespace App\Services;

use App\Models\UserModel;

/**
 * Self-service "forgot password" flow. Two deliberate anti-enumeration
 * choices, matching UserModel::authenticateUser()'s existing philosophy of
 * never letting a response reveal whether an account exists:
 *
 *  - request() always returns the same generic outcome whether or not the
 *    username/email matched a real, active account. Any internal failure
 *    (no SMTP configured, send error) is logged, never surfaced to the
 *    requester -- an error message that only appears for real accounts
 *    would itself leak which accounts are real.
 *  - The reset token is stored as a SHA-256 hash, exactly as passwords are
 *    stored as bcrypt hashes -- reading the users table does not hand you a
 *    working reset link, only verifying a submitted one does.
 */
class PasswordResetService
{
    private const TOKEN_VALID_MINUTES = 60;

    protected UserModel $userModel;
    protected MailSettingsService $mailSettingsService;
    protected EmailTemplateService $emailTemplateService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->userModel            = new UserModel();
        $this->mailSettingsService  = new MailSettingsService();
        $this->emailTemplateService = new EmailTemplateService();
        $this->activityLogService   = new ActivityLogService();
    }

    /**
     * Looks up the account and, if it exists, is active and has an email on
     * file, issues a token and emails the link. The caller-facing outcome is
     * identical regardless of what actually happened internally.
     */
    public function request(string $usernameOrEmail): void
    {
        $usernameOrEmail = trim($usernameOrEmail);
        if ($usernameOrEmail === '') {
            return;
        }

        $user = filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL)
            ? $this->userModel->findByEmail($usernameOrEmail)
            : $this->userModel->findByUsername($usernameOrEmail);

        if ($user === null || (int) ($user['is_active'] ?? 0) === 0) {
            return;
        }

        $email = (string) ($user['email'] ?? '');
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // No usable address to send to -- nothing more can happen, and
            // silently doing nothing keeps the same generic outcome for the
            // requester as a not-found account.
            log_message('info', '[PasswordResetService] Reset requested for {u}, but no valid email is on file.', ['u' => $user['username']]);
            return;
        }

        if (! $this->mailSettingsService->isConfigured()) {
            log_message('error', '[PasswordResetService] Reset requested for {u}, but SMTP is not configured.', ['u' => $user['username']]);
            return;
        }

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_VALID_MINUTES * 60);

        $this->userModel->setResetToken((int) $user['id'], $tokenHash, $expiresAt);

        $resetLink = base_url('auth/resetPassword/' . $rawToken);

        $rendered = $this->emailTemplateService->render('password_reset', [
            'full_name'  => $user['full_name'] ?: $user['username'],
            'username'   => $user['username'],
            'reset_link' => $resetLink,
            'valid_for'  => self::TOKEN_VALID_MINUTES . ' minutes',
        ]);

        $this->deliver($email, $rendered['subject'], $rendered['body']);

        $this->activityLogService->record('auth.password_reset.request', 'user', (int) $user['id'], 'Password reset requested for ' . $user['username']);
    }

    /**
     * @throws \InvalidArgumentException on an invalid/expired token or a weak password
     */
    public function resetWithToken(#[\SensitiveParameter] string $rawToken, #[\SensitiveParameter] string $newPassword, #[\SensitiveParameter] string $confirmPassword): void
    {
        $rawToken = trim($rawToken);
        if ($rawToken === '') {
            throw new \InvalidArgumentException('This reset link is invalid.');
        }

        $user = $this->userModel->findByValidResetTokenHash(hash('sha256', $rawToken));
        if ($user === null) {
            throw new \InvalidArgumentException('This reset link is invalid or has expired. Request a new one.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new \InvalidArgumentException('The two passwords do not match.');
        }

        // One shared rule rather than a second copy of the bounds. The length
        // used to be hardcoded here as well, which is exactly how this path and
        // the admin one drift apart -- and a split rule would let one path set a
        // password the other would refuse.
        UserManagementService::assertPasswordPolicy($newPassword);

        $this->userModel->update((int) $user['id'], [
            'password_hash'    => password_hash($newPassword, PASSWORD_BCRYPT),
            'reset_token_hash' => null,
            'reset_expires_at' => null,
        ]);

        $this->activityLogService->record('auth.password_reset.complete', 'user', (int) $user['id'], 'Password reset completed for ' . $user['username']);
    }

    /** Whether a raw token currently resolves to a live, unexpired request -- for the reset-form page to check before rendering. */
    public function tokenIsValid(string $rawToken): bool
    {
        return $rawToken !== '' && $this->userModel->findByValidResetTokenHash(hash('sha256', $rawToken)) !== null;
    }

    private function deliver(string $to, string $subject, string $htmlBody): void
    {
        try {
            $settings = $this->mailSettingsService->getAll();

            $email = \Config\Services::email(null, false);
            $email->initialize($this->mailSettingsService->buildEmailConfig());
            $email->setFrom($settings[MailSettingsService::KEY_FROM_EMAIL], $settings[MailSettingsService::KEY_FROM_NAME] ?: 'E-Section');
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage($htmlBody);

            if (! $email->send(false)) {
                log_message('error', '[PasswordResetService] Send failed: {debug}', ['debug' => strip_tags($email->printDebugger(['headers']))]);
            }
        } catch (\Throwable $e) {
            log_message('error', '[PasswordResetService] {message}', ['message' => (string) $e]);
        }
    }
}
