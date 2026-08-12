<?php

namespace App\Controllers;

use App\Services\BulkEmailService;
use App\Services\EmailTemplateService;
use App\Services\MailSettingsService;

class SettingsMail extends BaseController
{
    protected MailSettingsService $mailSettingsService;
    protected EmailTemplateService $emailTemplateService;
    protected BulkEmailService $bulkEmailService;

    public function __construct()
    {
        $this->mailSettingsService  = new MailSettingsService();
        $this->emailTemplateService = new EmailTemplateService();
        $this->bulkEmailService     = new BulkEmailService();
    }

    public function index()
    {
        $templates = [];
        foreach ($this->emailTemplateService->getSlugs() as $slug) {
            $templates[$slug] = [
                'label'  => $this->emailTemplateService->getLabel($slug),
                'tokens' => $this->emailTemplateService->getTokens($slug),
                'fields' => $this->emailTemplateService->getFields($slug),
            ];
        }

        return view('settings/mail', [
            'title'     => 'Settings — Email',
            'settings'  => $this->mailSettingsService->getAll(),
            'templates' => $templates,
        ]);
    }

    public function store()
    {
        try {
            $this->mailSettingsService->save($this->request->getPost());
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->respondToPost(false, $e->getMessage(), base_url('settings/mail'));
        } catch (\Throwable $e) {
            log_message('error', '[SettingsMail::store] {message}', ['message' => (string) $e]);

            return $this->respondToPost(
                false,
                'The email settings could not be saved. The issue has been logged.',
                base_url('settings/mail'),
                [],
                500
            );
        }

        $extra = [];

        if ($this->request->isAJAX()) {
            $settings = $this->mailSettingsService->getAll();

            $extra = [
                'html'  => view('settings/_mail_status', ['settings' => $settings]),
                'state' => ['password_configured' => (bool) $settings['password_configured']],
            ];
        }

        return $this->respondToPost(true, 'Email settings saved.', base_url('settings/mail'), $extra);
    }

    public function storeTemplate($slug)
    {
        try {
            $this->emailTemplateService->save((string) $slug, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respondToPost(false, $e->getMessage(), base_url('settings/mail'));
        }

        return $this->respondToPost(true, 'Email template saved.', base_url('settings/mail'));
    }

    /** Proves the SMTP settings work before any real recipient is contacted. */
    public function sendTest()
    {
        set_time_limit(0);

        try {
            $this->bulkEmailService->sendTest((string) $this->request->getPost('test_email'));
        } catch (\InvalidArgumentException $e) {
            return $this->respondToPost(false, $e->getMessage(), base_url('settings/mail'));
        } catch (\Throwable $e) {
            log_message('error', '[SettingsMail::sendTest] {message}', ['message' => (string) $e]);

            return $this->respondToPost(
                false,
                'The test email could not be sent. The issue has been logged.',
                base_url('settings/mail'),
                [],
                500
            );
        }

        return $this->respondToPost(
            true,
            'Test email sent. Check the inbox to confirm it arrived.',
            base_url('settings/mail')
        );
    }
}
