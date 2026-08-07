<?php

namespace App\Controllers;

use App\Models\EmailLogModel;
use App\Services\BulkEmailService;
use App\Services\EmailTemplateService;
use App\Services\MailSettingsService;

/**
 * Compose -> preview -> confirm -> send, plus the send log.
 *
 * The preview step is mandatory by design: index() only ever RESOLVES
 * recipients, it never sends. Sending requires a second, explicit POST to
 * send(). Nothing can leave the system on a single click.
 */
class BulkEmail extends BaseController
{
    protected BulkEmailService $bulkEmailService;
    protected EmailTemplateService $emailTemplateService;
    protected MailSettingsService $mailSettingsService;
    protected EmailLogModel $emailLogModel;

    public function __construct()
    {
        $this->bulkEmailService     = new BulkEmailService();
        $this->emailTemplateService = new EmailTemplateService();
        $this->mailSettingsService  = new MailSettingsService();
        $this->emailLogModel        = new EmailLogModel();
    }

    /** Compose screen; also renders the resolved recipient preview after a filter submit. */
    public function index()
    {
        $audience = sanitize_xss($this->request->getGet('audience') ?? BulkEmailService::AUDIENCE_UNIVERSITY);
        if (! in_array($audience, [BulkEmailService::AUDIENCE_UNIVERSITY, BulkEmailService::AUDIENCE_STUDENT], true)) {
            $audience = BulkEmailService::AUDIENCE_UNIVERSITY;
        }

        // trim(), NOT sanitize_xss() -- see Reminders::university(). These
        // feed recipient resolution, so an encoded term does not merely show
        // an empty screen: it would silently narrow who receives the mail.
        // ($audience above keeps its guard because it is checked against an
        // allowlist on the very next line, not bound into a query.)
        $filters = [
            'state'  => trim((string) ($this->request->getGet('state') ?? '')),
            'year'   => trim((string) ($this->request->getGet('year') ?? '')),
            'stream' => trim((string) ($this->request->getGet('stream') ?? '')),
        ];

        // Only resolve once the operator has actually asked to preview --
        // otherwise opening the page would scan every student on every visit.
        $preview = null;
        if ($this->request->getGet('preview') !== null) {
            $preview = $this->bulkEmailService->resolveRecipients($audience, $filters);
        }

        return view('bulk_email/index', [
            'title'        => 'Send Emails',
            'audience'     => $audience,
            'filters'      => $filters,
            'preview'      => $preview,
            'slug'         => $audience === BulkEmailService::AUDIENCE_UNIVERSITY
                ? 'university_reminder'
                : 'student_document_reminder',
            'templateLabel' => $audience === BulkEmailService::AUDIENCE_UNIVERSITY
                ? $this->emailTemplateService->getLabel('university_reminder')
                : $this->emailTemplateService->getLabel('student_document_reminder'),
            'mailReady'    => $this->mailSettingsService->isConfigured(),
            'maxRecipients' => BulkEmailService::MAX_RECIPIENTS,
        ]);
    }

    /**
     * Actually sends. Re-resolves the recipient list server-side from the
     * same filters rather than trusting a posted list of addresses -- a
     * tampered POST must not be able to mail arbitrary addresses.
     */
    public function send()
    {
        set_time_limit(0);

        $audience = sanitize_xss($this->request->getPost('audience') ?? '');
        $slug     = sanitize_xss($this->request->getPost('template_slug') ?? '');
        // Must use exactly the same treatment as index() above: send()
        // re-resolves the recipient list from these filters server-side, so
        // any difference between the two would mean the operator approved
        // one preview and the system mailed a different set of people.
        $filters  = [
            'state'  => trim((string) ($this->request->getPost('state') ?? '')),
            'year'   => trim((string) ($this->request->getPost('year') ?? '')),
            'stream' => trim((string) ($this->request->getPost('stream') ?? '')),
        ];

        try {
            $resolved = $this->bulkEmailService->resolveRecipients($audience, $filters);
            $result   = $this->bulkEmailService->send($audience, $slug, $resolved['sendable']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('bulk-email'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[BulkEmail::send] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('bulk-email'))
                ->with('error', 'The emails could not be sent. The issue has been logged.');
        }

        $message = "Sent {$result['sent']} email(s).";
        if ($result['failed'] > 0) {
            $message .= " {$result['failed']} failed -- see the send log to review and retry.";
        }

        return redirect()->to(base_url('bulk-email/log'))->with('success', $message);
    }

    /** Searchable record of every email, with delivery status. */
    public function log()
    {
        // $search is a free-text LIKE over recipient addresses and subjects,
        // so it hits the encoding bug hardest -- searching for an address at
        // a "&"-containing domain, or a subject with an apostrophe, matched
        // nothing. $status keeps its guard: it is allowlisted just below.
        $status = sanitize_xss($this->request->getGet('status') ?? '');
        $search = trim((string) ($this->request->getGet('q') ?? ''));

        if (! in_array($status, ['', 'sent', 'failed'], true)) {
            $status = '';
        }

        return view('bulk_email/log', [
            'title'  => 'Sent Emails',
            'rows'   => $this->emailLogModel->searchLog($status, $search),
            'pager'  => $this->emailLogModel->pager,
            'counts' => $this->emailLogModel->statusCounts(),
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function retry($id)
    {
        set_time_limit(0);

        try {
            $ok = $this->bulkEmailService->retry((int) $id);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[BulkEmail::retry] {message}', ['message' => (string) $e]);

            return redirect()->back()->with('error', 'The retry could not be completed. The issue has been logged.');
        }

        return redirect()->back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'Email resent successfully.' : 'The retry failed again -- see the error on the log row.'
        );
    }

    /** Retry every failed message in one action. */
    public function retryAllFailed()
    {
        set_time_limit(0);

        $failed = $this->emailLogModel->getFailed();

        if ($failed === []) {
            return redirect()->to(base_url('bulk-email/log'))->with('error', 'There are no failed emails to retry.');
        }

        $ok = 0;
        foreach ($failed as $row) {
            try {
                if ($this->bulkEmailService->retry((int) $row['id'])) {
                    $ok++;
                }
            } catch (\Throwable $e) {
                log_message('error', '[BulkEmail::retryAllFailed] {message}', ['message' => (string) $e]);
            }
        }

        return redirect()->to(base_url('bulk-email/log'))
            ->with('success', $ok . ' of ' . count($failed) . ' failed email(s) resent successfully.');
    }
}
