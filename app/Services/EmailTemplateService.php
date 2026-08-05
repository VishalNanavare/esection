<?php

namespace App\Services;

/**
 * Editable email wording, mirroring LetterTemplateService's shape so the two
 * feel identical to an admin: a known-slug whitelist, defaults that ship as
 * sensible working text, and {token} placeholders the system fills in per
 * recipient.
 *
 * Kept separate from LetterTemplateService rather than bolted onto it: those
 * are PDF letters with subject/body/closing rendered into a print layout,
 * these are emails with a subject line and an HTML body. Sharing one class
 * would mean every method growing an "is this a letter or an email?" branch.
 */
class EmailTemplateService
{
    private const TEMPLATES = [
        'university_reminder' => [
            'label'  => 'University Verification Reminder',
            'tokens' => ['university_name', 'academic_year', 'course', 'pending_count'],
        ],
        'student_document_reminder' => [
            'label'  => 'Candidate Document Reminder',
            'tokens' => ['student_name', 'case_no', 'course', 'missing_document'],
        ],
        'password_reset' => [
            'label'  => 'Password Reset',
            'tokens' => ['full_name', 'username', 'reset_link', 'valid_for'],
        ],
    ];

    private const DEFAULTS = [
        'university_reminder' => [
            'subject' => 'Pending Eligibility Verification - {university_name} ({academic_year})',
            'body'    => "Respected Sir/Madam,\n\nThis is a reminder regarding the eligibility verification of candidates admitted to {course} for the academic year {academic_year}.\n\nAs per our records, {pending_count} case(s) referred to {university_name} are still awaiting verification of the marksheets/certificates submitted by the candidates.\n\nYou are requested to verify the said documents and communicate the outcome to this office at the earliest, so that the admissions can be regularised.\n\nThank you for your co-operation.",
        ],
        'student_document_reminder' => [
            'subject' => 'Documents Pending for Eligibility - Case {case_no}',
            'body'    => "Dear {student_name},\n\nYour eligibility case ({case_no}) for admission to {course} cannot be processed further because the following document(s) are still awaited:\n\n{missing_document}\n\nYou are requested to submit the above document(s) to the IDOL Eligibility Section at the earliest. Admission remains provisional until the eligibility is confirmed.\n\nIf you have already submitted these documents, please ignore this message.",
        ],
        'password_reset' => [
            'subject' => 'Reset your E-Section password',
            'body'    => "Dear {full_name},\n\nA password reset was requested for your E-Section account ({username}).\n\nUse the link below to choose a new password. It is valid for {valid_for}.\n\n{reset_link}\n\nIf you did not request this, you can ignore this message -- your password will not change.",
        ],
    ];

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
    }

    /** @return string[] */
    public function getSlugs(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public function getLabel(string $slug): string
    {
        $this->assertValidSlug($slug);

        return self::TEMPLATES[$slug]['label'];
    }

    /** @return string[] */
    public function getTokens(string $slug): array
    {
        $this->assertValidSlug($slug);

        return self::TEMPLATES[$slug]['tokens'];
    }

    /**
     * Saved wording, falling back to the shipped default so an unconfigured
     * template is still fully usable rather than blank.
     *
     * @return array{subject: string, body: string}
     */
    public function getFields(string $slug): array
    {
        $this->assertValidSlug($slug);

        $values = $this->settingService->getMany(["email_{$slug}_subject", "email_{$slug}_body"]);

        return [
            'subject' => (string) ($values["email_{$slug}_subject"] ?? '') !== ''
                ? (string) $values["email_{$slug}_subject"]
                : self::DEFAULTS[$slug]['subject'],
            'body' => (string) ($values["email_{$slug}_body"] ?? '') !== ''
                ? (string) $values["email_{$slug}_body"]
                : self::DEFAULTS[$slug]['body'],
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function save(string $slug, array $postData): void
    {
        $this->assertValidSlug($slug);

        $subject = trim(sanitize_xss($postData['subject'] ?? ''));
        $body    = trim(sanitize_xss($postData['body'] ?? ''));

        if ($subject === '') {
            throw new \InvalidArgumentException('The email subject cannot be empty.');
        }
        if ($body === '') {
            throw new \InvalidArgumentException('The email body cannot be empty.');
        }

        $this->settingService->set("email_{$slug}_subject", $subject, 'email_templates');
        $this->settingService->set("email_{$slug}_body", $body, 'email_templates');

        $this->activityLogService->record(
            'settings.email_template.update',
            'email_template',
            null,
            'Updated the ' . $this->getLabel($slug) . ' email template'
        );
    }

    /**
     * Substitutes {tokens} for one recipient. The single render point used by
     * the preview, the real send and the retry, so a preview can never drift
     * from what actually goes out.
     *
     * The body is esc()'d then nl2br()'d: an admin types plain sentences and
     * gets valid HTML, and any angle brackets they type are neutralised
     * rather than injected into the message.
     *
     * @param  array<string, mixed> $tokenValues
     * @return array{subject: string, body: string} subject is plain text, body is HTML
     */
    public function render(string $slug, array $tokenValues): array
    {
        $fields = $this->getFields($slug);

        $subjectReplacements = [];
        $bodyReplacements    = [];
        foreach ($tokenValues as $token => $value) {
            // Subject lines are plain text -- HTML-escaping there would leak
            // entities like &amp; into the inbox subject.
            $subjectReplacements['{' . $token . '}'] = (string) $value;
            $bodyReplacements['{' . $token . '}']    = esc((string) $value);
        }

        return [
            'subject' => strtr($fields['subject'], $subjectReplacements),
            'body'    => strtr(nl2br(esc($fields['body'])), $bodyReplacements),
        ];
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertValidSlug(string $slug): void
    {
        if (! isset(self::TEMPLATES[$slug])) {
            throw new \InvalidArgumentException('Unknown email template.');
        }
    }
}
