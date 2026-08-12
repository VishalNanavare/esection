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
            return $this->respondToPost(false, $e->getMessage(), base_url('bulk-email'));
        } catch (\Throwable $e) {
            log_message('error', '[BulkEmail::send] {message}', ['message' => (string) $e]);

            return $this->respondToPost(
                false,
                'The emails could not be sent. The issue has been logged.',
                base_url('bulk-email'),
                [],
                500
            );
        }

        $message = "Sent {$result['sent']} email(s).";
        if ($result['failed'] > 0) {
            $message .= " {$result['failed']} failed -- see the send log to review and retry.";
        }

        // Still lands on the send log, exactly as before.
        //
        // The navigation is not incidental here, it is a safety property: it
        // destroys the compose form, so the same batch cannot be sent twice by
        // an operator who missed the toast and clicked again. send() has no
        // server-side lock, and these recipients are external universities.
        // What AJAX adds is that the POST never enters browser history, so a
        // refresh on the log no longer offers to resubmit it.
        return $this->respondToPost(true, $message, base_url('bulk-email/log'), [
            'redirect_url' => base_url('bulk-email/log'),
        ]);
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
            return $this->respondForLog(false, $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[BulkEmail::retry] {message}', ['message' => (string) $e]);

            return $this->respondForLog(false, 'The retry could not be completed. The issue has been logged.', 500);
        }

        return $this->respondForLog(
            $ok,
            $ok ? 'Email resent successfully.' : 'The retry failed again -- see the error on the log row.'
        );
    }

    /** Retry every failed message in one action. */
    public function retryAllFailed()
    {
        set_time_limit(0);

        $failed = $this->emailLogModel->getFailed();

        if ($failed === []) {
            return $this->respondForLog(false, 'There are no failed emails to retry.');
        }

        // Same ceiling send() already applies. getFailed() is unbounded, and
        // each retry opens its own SMTP connection: nginx closes a FastCGI read
        // at 600s while PHP carries on sending, so an unbounded run reports a
        // failure the operator cannot trust and invites a duplicate retry.
        $total     = count($failed);
        $truncated = $total > BulkEmailService::MAX_RECIPIENTS;

        if ($truncated) {
            $failed = array_slice($failed, 0, BulkEmailService::MAX_RECIPIENTS);
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

        $message = $ok . ' of ' . count($failed) . ' failed email(s) resent successfully.';
        if ($truncated) {
            $message .= ' Only the first ' . BulkEmailService::MAX_RECIPIENTS . ' of ' . $total
                . ' were attempted -- run it again for the rest.';
        }

        return $this->respondForLog(true, $message);
    }

    /**
     * Reply for the two log POSTs.
     *
     * Four regions, not one. A retry is an UPDATE, so the row set only changes
     * when a status filter is active -- but the "Retry all failed (N)" block can
     * vanish entirely, the <select> labels come from an UNFILTERED count query
     * and so move on every retry regardless of the active filter, and the pager
     * can change. Repainting only the tbody would leave the header still
     * offering to retry emails that have just succeeded.
     *
     * Fallback stays redirect()->back() (null) so a no-JavaScript operator
     * returns to their filtered, paginated log rather than page 1 of everything.
     */
    private function respondForLog(bool $ok, string $message, int $failStatus = 422)
    {
        $extra = [];

        if ($this->request->isAJAX()) {
            $status = sanitize_xss($this->request->getGet('status') ?? '');
            $search = trim((string) ($this->request->getGet('q') ?? ''));

            if (! in_array($status, ['', 'sent', 'failed'], true)) {
                $status = '';
            }

            $rows   = $this->emailLogModel->searchLog($status, $search);
            $pager  = $this->emailLogModel->pager;
            $counts = $this->emailLogModel->statusCounts();

            if ($pager !== null) {
                // Without this the pager would build its links from the current
                // request URI -- a POST-only retry route.
                $pager->setPath('bulk-email/log');
            }

            $extra = [
                'html'        => view('bulk_email/_log_rows', ['rows' => $rows, 'status' => $status, 'search' => $search]),
                'actionsHtml' => view('bulk_email/_log_actions', ['counts' => $counts]),
                'filterHtml'  => view('bulk_email/_log_status_filter', ['counts' => $counts, 'status' => $status]),
                'pagerHtml'   => $pager !== null ? $pager->links('default', 'glass') : '',
            ];
        }

        return $this->respondToPost($ok, $message, null, $extra, $failStatus);
    }
}
