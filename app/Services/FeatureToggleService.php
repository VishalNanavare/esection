<?php

namespace App\Services;

class FeatureToggleService
{
    private const KNOWN_FLAGS = [
        'feature_export_enabled',
        'feature_bulk_email_enabled',
        'feature_delete_enabled',
    ];

    private const LABELS = [
        'feature_export_enabled'     => 'Excel Export',
        'feature_bulk_email_enabled' => 'Bulk Email',
        'feature_delete_enabled'     => 'Delete Records',
    ];

    protected SettingService $settingService;
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->settingService     = new SettingService();
        $this->activityLogService = new ActivityLogService();
    }

    /**
     * @return array<string, array{label: string, enabled: bool}>
     */
    public function getAll(): array
    {
        $values = $this->settingService->getMany(self::KNOWN_FLAGS);

        $flags = [];
        foreach (self::KNOWN_FLAGS as $key) {
            $flags[$key] = [
                'label'   => self::LABELS[$key],
                'enabled' => (bool) (int) ($values[$key] ?? 0),
            ];
        }

        return $flags;
    }

    /**
     * Writes ONLY keys in KNOWN_FLAGS -- this whitelist is the actual
     * security boundary, since SettingService::set() itself has no such
     * restriction. An unchecked checkbox is absent from $postData entirely,
     * so "key absent" must mean false, not "leave the old value alone".
     */
    public function save(array $postData): void
    {
        $before  = $this->getAll();
        $changed = [];

        foreach (self::KNOWN_FLAGS as $key) {
            $newValue = ! empty($postData[$key]) ? '1' : '0';
            $oldValue = $before[$key]['enabled'] ? '1' : '0';

            if ($newValue !== $oldValue) {
                $changed[] = self::LABELS[$key] . ': ' . ($newValue === '1' ? 'on' : 'off');
            }

            $this->settingService->set($key, $newValue, 'feature');
        }

        if ($changed !== []) {
            $this->activityLogService->record('feature_toggles.update', 'settings', null, implode(', ', $changed));
        }
    }
}
