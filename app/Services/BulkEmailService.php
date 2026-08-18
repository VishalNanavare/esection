<?php

namespace App\Services;

use App\Models\CollegeModel;
use App\Models\EmailLogModel;
use App\Models\StudentModel;

/**
 * Bulk email: resolve recipients, show them BEFORE anything is sent, then
 * send in throttled batches and log every outcome.
 *
 * The "preview before send" step is not a nicety -- it is the whole safety
 * model. Nothing leaves the system until the operator has seen the exact
 * list, with unusable addresses already marked and excluded.
 */
class BulkEmailService
{
    public const AUDIENCE_UNIVERSITY = 'university';
    public const AUDIENCE_STUDENT    = 'student';

    /** Hard ceiling per run, so one click cannot queue an unbounded send. */
    public const MAX_RECIPIENTS = 500;

    protected MailSettingsService $mailSettingsService;
    protected EmailTemplateService $emailTemplateService;
    protected EmailLogModel $emailLogModel;
    protected CollegeModel $collegeModel;
    protected StudentModel $studentModel;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->mailSettingsService  = new MailSettingsService();
        $this->emailTemplateService = new EmailTemplateService();
        $this->emailLogModel        = new EmailLogModel();
        $this->collegeModel         = new CollegeModel();
        $this->studentModel         = new StudentModel();
        $this->activityLogService   = new ActivityLogService();
    }

    /**
     * Resolve the recipient list for a filter set, splitting it into those
     * that can be mailed and those that cannot (with the reason).
     *
     * Both lists are returned: the operator needs to SEE who is being skipped
     * and why, otherwise a silently-dropped recipient looks like a delivered
     * one.
     *
     * @return array{sendable: array<int, array<string, mixed>>, skipped: array<int, array<string, mixed>>, truncated: bool}
     */
    public function resolveRecipients(string $audience, array $filters): array
    {
        $rows = $audience === self::AUDIENCE_UNIVERSITY
            ? $this->collegeModel->getForBulkEmail((string) ($filters['state'] ?? ''))
            : $this->studentModel->getForBulkEmail(
                (string) ($filters['year'] ?? ''),
                (string) ($filters['stream'] ?? '')
            );

        $sendable = [];
        $skipped   = [];
        $seen      = [];

        foreach ($rows as $row) {
            $email = trim((string) ($row['email'] ?? ''));
            $name  = (string) ($row['name'] ?? '');

            if ($email === '') {
                $skipped[] = ['name' => $name, 'email' => '', 'reason' => 'No email address on record'];
                continue;
            }

            // Real data here is dirty: college_details.email_id holds values
            // like "NEFT or ECS" and "a@x.ac.in / b@y.ac.in". Anything that
            // is not a single valid address is surfaced, never guessed at.
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = ['name' => $name, 'email' => $email, 'reason' => 'Not a valid email address'];
                continue;
            }

            $key = strtolower($email);
            if (isset($seen[$key])) {
                $skipped[] = ['name' => $name, 'email' => $email, 'reason' => 'Duplicate address (already in this list)'];
                continue;
            }
            $seen[$key] = true;

            $sendable[] = [
                'id'    => (int) ($row['id'] ?? 0),
                'name'  => $name,
                'email' => $email,
                'meta'  => $row,
            ];
        }

        $truncated = count($sendable) > self::MAX_RECIPIENTS;
        if ($truncated) {
            $sendable = array_slice($sendable, 0, self::MAX_RECIPIENTS);
        }

        return ['sendable' => $sendable, 'skipped' => $skipped, 'truncated' => $truncated];
    }

    /**
     * Send to a resolved list, in batches, pausing between them.
     *
     * @return array{batch_ref: string, sent: int, failed: int}
     * @throws \InvalidArgumentException when the feature or SMTP is not usable
     */
    public function send(string $audience, string $slug, array $recipients): array
    {
        if (! feature_enabled('feature_bulk_email_enabled')) {
            throw new \InvalidArgumentException('Bulk email is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }
        if (! $this->mailSettingsService->isConfigured()) {
            throw new \InvalidArgumentException('Email is not configured yet. Set the SMTP server and "from" address in Settings > Email first.');
        }
        if ($recipients === []) {
            throw new \InvalidArgumentException('There are no valid recipients to send to.');
        }

        $settings   = $this->mailSettingsService->getAll();
        $batchSize  = (int) $settings[MailSettingsService::KEY_BATCH_SIZE];
        $pause      = (int) $settings[MailSettingsService::KEY_BATCH_PAUSE];
        $batchRef   = date('YmdHis') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $sentBy     = (string) (session()->get('username') ?? 'System');

        $sent = 0;
        $failed = 0;
        $processed = 0;

        foreach ($recipients as $recipient) {
            $rendered = $this->emailTemplateService->render($slug, $this->tokenValuesFor($audience, $recipient));

            $result = $this->deliver($recipient['email'], $recipient['name'], $rendered['subject'], $rendered['body']);

            $this->emailLogModel->insert([
                'batch_ref'       => $batchRef,
                'template_slug'   => $slug,
                'recipient_type'  => $audience,
                'recipient_id'    => $recipient['id'] ?: null,
                'recipient_name'  => $recipient['name'],
                'recipient_email' => $recipient['email'],
                'subject'         => mb_substr($rendered['subject'], 0, 255),
                'status'          => $result['ok'] ? 'sent' : 'failed',
                'error_message'   => $result['ok'] ? null : $result['error'],
                'attempts'        => 1,
                'sent_by'         => $sentBy,
            ]);

            $result['ok'] ? $sent++ : $failed++;
            $processed++;

            // Throttle: pause between batches so the mail server is not
            // flooded and the messages are less likely to be graded as spam.
            if ($pause > 0 && $processed % $batchSize === 0 && $processed < count($recipients)) {
                sleep($pause);
            }
        }

        $this->activityLogService->record(
            'email.bulk_send',
            'email_log',
            null,
            "Bulk email ({$slug}) to {$audience}: {$sent} sent, {$failed} failed [{$batchRef}]"
        );

        return ['batch_ref' => $batchRef, 'sent' => $sent, 'failed' => $failed];
    }

    /**
     * Re-attempt one previously failed message. Updates the existing log row
     * rather than inserting a new one, so the log stays one row per intended
     * recipient with an attempt count -- not a growing pile of duplicates.
     *
     * @throws \InvalidArgumentException
     */
    public function retry(int $logId): bool
    {
        // Same kill switch send() honours. Without it the toggle stopped new
        // batches but not retries -- and retryAllFailed() loops through here up
        // to MAX_RECIPIENTS times, so an admin who switched Bulk Email OFF
        // *because* a bad batch had just gone out could still push 500 more real
        // messages to external addresses from the log screen. The one control
        // reached for during an incident has to fail closed.
        if (! feature_enabled('feature_bulk_email_enabled')) {
            throw new \InvalidArgumentException('Bulk email is currently disabled. Ask an administrator to enable it in Settings > Feature Toggles.');
        }

        $row = $this->emailLogModel->findOneById($logId);

        if ($row === null) {
            throw new \InvalidArgumentException('That email log entry no longer exists.');
        }
        if ($row['status'] === 'sent') {
            throw new \InvalidArgumentException('That email was already delivered successfully.');
        }
        if (! $this->mailSettingsService->isConfigured()) {
            throw new \InvalidArgumentException('Email is not configured yet.');
        }

        // Re-render from the CURRENT template, so fixing the wording and
        // retrying actually sends the corrected message.
        $rendered = $this->emailTemplateService->render(
            (string) $row['template_slug'],
            $this->tokenValuesFor((string) $row['recipient_type'], [
                'id'    => (int) $row['recipient_id'],
                'name'  => (string) $row['recipient_name'],
                'email' => (string) $row['recipient_email'],
                'meta'  => [],
            ])
        );

        $result = $this->deliver(
            (string) $row['recipient_email'],
            (string) $row['recipient_name'],
            $rendered['subject'],
            $rendered['body']
        );

        $this->emailLogModel->markResult($logId, $result['ok'], $result['error']);

        return $result['ok'];
    }

    /**
     * Send a single test message, to prove the SMTP settings work before any
     * real recipient is contacted.
     *
     * @throws \InvalidArgumentException
     */
    public function sendTest(string $toEmail): void
    {
        $toEmail = trim($toEmail);

        if ($toEmail === '' || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid email address to send the test to.');
        }
        if (! $this->mailSettingsService->isConfigured()) {
            throw new \InvalidArgumentException('Set the SMTP server and "from" address first, then save, then send a test.');
        }

        $result = $this->deliver(
            $toEmail,
            '',
            'E-Section test email',
            '<p>This is a test message from E-Section.</p><p>If you are reading it, your SMTP settings are working.</p>'
        );

        if (! $result['ok']) {
            throw new \InvalidArgumentException('The test email could not be sent: ' . $result['error']);
        }
    }

    /** @var \CodeIgniter\Email\Email|null Built once per request, reused for every message. */
    private $mailerInstance;

    /** @var array<string, mixed> */
    private array $mailerSettings = [];

    /**
     * The mail transport, built once instead of once per recipient.
     *
     * deliver() used to construct a fresh Email object AND re-read the SMTP
     * settings from the database on every single message. A 500-recipient run
     * therefore made a thousand settings queries to send five hundred mails,
     * before any of the actual sending.
     *
     * SMTPKeepAlive is deliberately NOT enabled, even though it is the obvious
     * next step and would collapse 500 TCP+TLS+AUTH handshakes into one.
     * CodeIgniter cannot survive the connection being dropped mid-run:
     * isSMTPConnected() (Email.php:2251) only checks that the local resource is
     * not closed, so a socket the server has timed out still reads as live and
     * the remaining messages are written into a dead connection. Worse,
     * Email.php:2024 sets SMTPAuth = false once keep-alive is on, so even a
     * genuine reconnect would skip authentication and be refused. Trading five
     * hundred handshakes for "everything after the first idle timeout fails
     * silently" is the wrong way round.
     *
     * @return array{0: \CodeIgniter\Email\Email, 1: array<string, mixed>}
     */
    private function mailer(): array
    {
        if ($this->mailerInstance === null) {
            $this->mailerSettings = $this->mailSettingsService->getAll();

            $this->mailerInstance = \Config\Services::email(null, false);
            $this->mailerInstance->initialize($this->mailSettingsService->buildEmailConfig());
        }

        return [$this->mailerInstance, $this->mailerSettings];
    }

    /**
     * The one place a message actually leaves the system.
     *
     * @return array{ok: bool, error: string}
     */
    private function deliver(string $to, string $toName, string $subject, string $htmlBody): array
    {
        try {
            [$email, $settings] = $this->mailer();

            // The instance is shared across the run now, and send(false) never
            // auto-clears, so reset the message state explicitly. setTo() and
            // friends do replace rather than append, so this is belt and
            // braces -- but a future CC or attachment would otherwise leak
            // from one recipient's mail into the next one's, which is the kind
            // of bug that is only noticed after it has been sent.
            $email->clear(true);

            $email->setFrom(
                $settings[MailSettingsService::KEY_FROM_EMAIL],
                $settings[MailSettingsService::KEY_FROM_NAME] ?: 'E-Section'
            );
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage($htmlBody);

            if ($email->send(false)) {
                return ['ok' => true, 'error' => ''];
            }

            // printDebugger() carries the SMTP conversation, which is exactly
            // what makes a failure diagnosable later. Trimmed so one bad send
            // cannot bloat the log table.
            return ['ok' => false, 'error' => mb_substr(strip_tags($email->printDebugger(['headers'])), 0, 1000)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 1000)];
        }
    }

    /**
     * @param  array<string, mixed> $recipient
     * @return array<string, string>
     */
    private function tokenValuesFor(string $audience, array $recipient): array
    {
        $meta = $recipient['meta'] ?? [];

        if ($audience === self::AUDIENCE_UNIVERSITY) {
            return [
                'university_name' => $recipient['name'],
                'academic_year'   => (string) ($meta['academic_year'] ?? ''),
                'course'          => (string) ($meta['course'] ?? ''),
                'pending_count'   => (string) ($meta['pending_count'] ?? ''),
            ];
        }

        return [
            'student_name'     => $recipient['name'],
            'case_no'          => (string) ($meta['eligibility_case_no'] ?? ''),
            'course'           => (string) ($meta['admission_taken_in'] ?? ''),
            'missing_document' => (string) ($meta['missing_document'] ?? 'the pending document(s)'),
        ];
    }
}
