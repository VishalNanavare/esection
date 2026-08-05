<?php

namespace App\Services;

use App\Models\SettingModel;

class SettingService
{
    protected SettingModel $settingModel;

    public function __construct()
    {
        $this->settingModel = new SettingModel();
    }

    public function get(string $key, $default = null)
    {
        $row = $this->settingModel->findByKey($key);

        return $row ? $row['setting_value'] : $default;
    }

    /**
     * @param string[] $keys
     * @return array<string, mixed> value per key; unsaved keys come back null
     */
    public function getMany(array $keys): array
    {
        $rows = $this->settingModel->findByKeys($keys);

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $rows[$key]['setting_value'] ?? null;
        }

        return $values;
    }

    public function set(string $key, $value, ?string $group = null): void
    {
        $existing = $this->settingModel->findByKey($key);

        $data = [
            'setting_value' => $value,
            'updated_by'    => session()->get('id'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            if ($group !== null) {
                $data['setting_group'] = $group;
            }
            $this->settingModel->update($existing['id'], $data);
            return;
        }

        $data['setting_key']   = $key;
        $data['setting_group'] = $group;
        $this->settingModel->insert($data);
    }
}
