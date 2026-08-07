<?php

namespace App\Services;

class DocumentNumberingService
{
    private const KEY = 'case_no_prefix';

    /** How many times to re-roll a suggested case number that is already taken. */
    private const MAX_SUGGESTION_ATTEMPTS = 5;

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;
    protected \App\Models\StudentModel $studentModel;

    public function __construct()
    {
        helper('esection');
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
        $this->studentModel       = new \App\Models\StudentModel();
    }

    public function getPrefix(): string
    {
        return (string) $this->settingService->get(self::KEY, 'CASE');
    }

    /**
     * @throws \InvalidArgumentException on bad input
     */
    public function save(array $postData): void
    {
        $prefix = trim(sanitize_xss($postData['case_no_prefix'] ?? ''));
        if ($prefix === '') {
            throw new \InvalidArgumentException('Case number prefix is required.');
        }

        $prefix = strtoupper($prefix);
        $this->settingService->set(self::KEY, $prefix, 'numbering');

        $this->activityLogService->record('settings.numbering.update', 'settings', null, 'Set case number prefix to ' . $prefix);
    }

    /**
     * A preview of the FORMAT, not a sequential counter -- generate_case_no()
     * always ends in a random 4-digit suffix, so this can only ever show a
     * plausible example, never the number that will actually be issued next.
     *
     * It is now at least checked against the table before being offered.
     * The suffix is rand(1, 9999), i.e. 9,999 values per year, and nothing
     * ever compared a generated number against existing rows -- so by the
     * birthday bound a collision became more likely than not after only
     * ~118 cases in a year, and 218 duplicated case numbers already exist
     * in the data. A duplicate matters because eligibility_case_no is how
     * staff look a candidate up.
     *
     * Bounded retries, not a loop: the field stays fully editable, so if
     * every attempt collided the operator simply types their own. Returning
     * the last candidate is strictly no worse than the previous behaviour,
     * which never checked at all.
     */
    public function previewNext(): string
    {
        $prefix = $this->getPrefix();

        $candidate = generate_case_no($prefix);

        for ($attempt = 0; $attempt < self::MAX_SUGGESTION_ATTEMPTS; $attempt++) {
            if (! $this->studentModel->caseNoExists($candidate)) {
                return $candidate;
            }

            $candidate = generate_case_no($prefix);
        }

        return $candidate;
    }
}
