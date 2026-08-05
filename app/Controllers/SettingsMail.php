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
            return redirect()->to(base_url('settings/mail'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsMail::store] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/mail'))
                ->with('error', 'The email settings could not be saved. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/mail'))->with('success', 'Email settings saved.');
    }

    public function storeTemplate($slug)
    {
        try {
            $this->emailTemplateService->save((string) $slug, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/mail'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('settings/mail'))->with('success', 'Email template saved.');
    }

    /** Proves the SMTP settings work before any real recipient is contacted. */
    public function sendTest()
    {
        set_time_limit(0);

        try {
            $this->bulkEmailService->sendTest((string) $this->request->getPost('test_email'));
        } catch (\InvalidArgumentException $e) {
            return redirect()->to(base_url('settings/mail'))->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', '[SettingsMail::sendTest] {message}', ['message' => (string) $e]);

            return redirect()->to(base_url('settings/mail'))
                ->with('error', 'The test email could not be sent. The issue has been logged.');
        }

        return redirect()->to(base_url('settings/mail'))
            ->with('success', 'Test email sent. Check the inbox to confirm it arrived.');
    }
}
