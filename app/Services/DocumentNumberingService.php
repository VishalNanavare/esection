<?php

namespace App\Services;

class DocumentNumberingService
{
    private const KEY = 'case_no_prefix';

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
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
     */
    public function previewNext(): string
    {
        return generate_case_no($this->getPrefix());
    }
}
