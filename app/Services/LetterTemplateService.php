<?php

namespace App\Services;

class LetterTemplateService
{
    /**
     * Slug => [default subject, default body, default closing, valid
     * tokens]. Defaults are the EXACT wording every template hard-coded
     * before this feature existed -- so an unconfigured install renders
     * byte-identical letters, same principle as every other Settings
     * fallback (Institute Details, Document Numbering).
     */
    private const TEMPLATES = [
        'dispatch' => [
            'subject' => 'Verification of Marksheet / Passing Certificate / Migration Certificate.',
            'body'    => "Sir/Madam,\nI am to forward herewith the copies of Marksheet / Passing / Migration Certificates of the undermentioned candidate(s) who have been admitted to {course} course in this Institute during the academic year {academic_year} for verification.",
            'closing' => 'Kindly verify the authenticity of the attached document(s) from your office records and return the same duly verified at an early date.',
            'tokens'  => ['course', 'academic_year'],
        ],
        'regularization' => [
            'subject' => 'Eligibility Regularization of Candidate {student_name}.',
            'body'    => "Sir/Madam,\nWith reference to the eligibility verification for {student_name} (Eligibility Case No: {eligibility_case_no}) admitted to {passing_course} program, the submitted documents have been reviewed and regularized by this Institute.",
            'closing' => 'Kindly record the eligibility regularization status in your records.',
            'tokens'  => ['student_name', 'eligibility_case_no', 'passing_course'],
        ],
        'university_reminder' => [
            'subject' => '{reminder_type} - Verification of Marksheet / Passing Certificate.',
            'body'    => "Sir/Madam,\nThis is a {reminder_type} regarding the verification of marksheet/certificates of candidate(s) admitted to {course} during academic year {academic_year}.",
            'closing' => 'Kindly verify and return the confirmed verification report at your earliest convenience.',
            'tokens'  => ['reminder_type', 'course', 'academic_year'],
        ],
        'student_reminder' => [
            'subject' => 'Submission of Pending Original Documents for Eligibility Verification.',
            'body'    => "Dear Candidate,\nYou are hereby informed that your eligibility verification for {course_name} course is pending due to non-submission of the following document(s):\n\nMissing Documents: {missing_doc}",
            'closing' => 'Please submit the required original documents to the IDOL Eligibility Section within 15 days, failing which your admission eligibility may be cancelled.',
            'tokens'  => ['course_name', 'missing_doc'],
        ],
        'dispatch_accounts' => [
            'subject' => 'Verification of Document/s for the academic year {academic_year}.',
            'body'    => "Sir/Madam,\nUniversity of Mumbai has decided to verify the document/s of the student/s who have taken admission in our Institute of Distance and Open Learning, University of Mumbai, on the basis of earlier qualification.\nThe following student/s has/have taken admission in the University of Mumbai as their details mentioned below. I am enclosing here with xerox copy/copies of the marksheet/s for your ready reference.",
            'closing' => "You are requested to kindly verify the/their marksheet/s and confirm the validity of the same.\nI shall be grateful if you treat this matter as most urgent.\nKindly mentioned the reference number and date of this letter in your further communication.\nThanking you.",
            'tokens'  => ['academic_year'],
        ],
        'confirmation_eligibility' => [
            'subject' => 'Confirmation of Eligibility For the Academic Year {academic_year} of Course {course}.',
            'body'    => 'With reference to your letter No. __________________ dated __________________. I am to inform you that the eligibility of {student_count_phrase} is hereby confirmed for the admission to the program mention against their respective names in this University / Board.',
            'closing' => '',
            'tokens'  => ['academic_year', 'course', 'student_count_phrase'],
        ],
    ];

    private const FOOTER_DEPARTMENT_DEFAULT = 'IDOL Eligibility Section';

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        helper('esection');
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
    }

    /** @return string[] the 6 known slugs */
    public function getSlugs(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public function getLabel(string $slug): string
    {
        return match ($slug) {
            'dispatch'                 => 'Eligibility Verification Dispatch Letter',
            'regularization'           => 'Regularization Letter',
            'university_reminder'      => 'University Reminder Letter',
            'student_reminder'         => 'Candidate Document Reminder Letter',
            'dispatch_accounts'        => 'Accounts Copy (Dispatch with DD Amount)',
            'confirmation_eligibility' => 'Confirmation of Eligibility Letter',
            default                    => $slug,
        };
    }

    /** @return string[] */
    public function getTokens(string $slug): array
    {
        return self::TEMPLATES[$slug]['tokens'] ?? [];
    }

    /**
     * @return array{subject: string, body: string, closing: string} saved-or-default text
     * @throws \InvalidArgumentException for an unknown slug
     */
    public function getFields(string $slug): array
    {
        $this->assertValidSlug($slug);

        $defaults = self::TEMPLATES[$slug];
        $saved    = $this->settingService->getMany([
            "letter_{$slug}_subject",
            "letter_{$slug}_body",
            "letter_{$slug}_closing",
        ]);

        return [
            'subject' => $saved["letter_{$slug}_subject"] ?? $defaults['subject'],
            'body'    => $saved["letter_{$slug}_body"] ?? $defaults['body'],
            'closing' => $saved["letter_{$slug}_closing"] ?? $defaults['closing'],
        ];
    }

    public function getFooterDepartment(): string
    {
        return $this->settingService->get('letter_footer_department') ?? self::FOOTER_DEPARTMENT_DEFAULT;
    }

    /**
     * @throws \InvalidArgumentException on an unknown slug or an empty body
     */
    public function save(string $slug, array $postData): void
    {
        $this->assertValidSlug($slug);

        $body = trim(sanitize_xss($postData['body'] ?? ''));
        if ($body === '') {
            throw new \InvalidArgumentException('The letter body cannot be empty.');
        }

        $subject = sanitize_xss($postData['subject'] ?? '');
        $closing = sanitize_xss($postData['closing'] ?? '');

        $this->settingService->set("letter_{$slug}_subject", $subject, 'letter_templates');
        $this->settingService->set("letter_{$slug}_body", $body, 'letter_templates');
        $this->settingService->set("letter_{$slug}_closing", $closing, 'letter_templates');

        $this->activityLogService->record(
            'settings.letter_template.update',
            'letter_template',
            null,
            'Updated the ' . $this->getLabel($slug) . ' template'
        );
    }

    public function saveFooterDepartment(string $value): void
    {
        $this->settingService->set('letter_footer_department', sanitize_xss($value), 'letter_templates');
        $this->activityLogService->record(
            'settings.letter_template.footer_update',
            'letter_template',
            null,
            'Updated the letter footer department name'
        );
    }

    /**
     * The single substitution point used by BOTH real letter generation and
     * the "Preview PDF" button, so a preview can never drift from real
     * output. $tokenValues is a flat [token => rawValue] map; each {token}
     * in the subject/body/closing text is replaced with the value escaped
     * and wrapped in <strong> -- matching the bold emphasis every template
     * already gave these values before this feature existed. The admin
     * edits plain sentences; they never need to type HTML.
     *
     * @param array{subject: string, body: string, closing: string} $fields
     * @param array<string, mixed> $tokenValues
     * @return array{subject: string, body: string, closing: string} HTML-safe, echo unescaped
     * @throws \InvalidArgumentException for an unknown slug
     */
    public function render(string $slug, array $fields, array $tokenValues): array
    {
        $this->assertValidSlug($slug);

        $replacements = [];
        foreach ($tokenValues as $token => $value) {
            $replacements['{' . $token . '}'] = '<strong>' . esc((string) $value) . '</strong>';
        }

        $result = [];
        foreach (['subject', 'body', 'closing'] as $field) {
            $text          = nl2br(esc($fields[$field] ?? ''));
            $result[$field] = strtr($text, $replacements);
        }

        return $result;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertValidSlug(string $slug): void
    {
        if (! array_key_exists($slug, self::TEMPLATES)) {
            throw new \InvalidArgumentException('Unknown letter template.');
        }
    }
}
