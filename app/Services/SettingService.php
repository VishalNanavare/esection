<?php

namespace App\Services;

use App\Models\SettingModel;

class SettingService
{
    /**
     * Per-request memo of resolved setting values, keyed by setting_key.
     *
     * Settings are read far more often than the call sites suggest:
     * feature_enabled() resolves through here, and several views call it
     * once per rendered table row, so a single page could issue hundreds
     * of identical `SELECT ... WHERE setting_key = ?` queries for a value
     * that cannot change mid-request.
     *
     * Deliberately static rather than per-instance: the helper keeps one
     * SettingService, but services like MailSettingsService construct
     * their own, and two live caches could disagree after a write. One
     * shared map means there is exactly one answer per request.
     *
     * Safe because this is a per-request memo, not a persistent cache --
     * PHP discards statics at the end of every request, so a value saved
     * by one user is never served to the next. Every write path in the
     * app goes through set() below (SettingModel is used nowhere else),
     * and set() invalidates the key, so a save is immediately visible.
     *
     * Stores the raw stored value, or null when the key has never been
     * saved -- the caller's $default is applied on read, so two callers
     * passing different defaults for the same unsaved key both stay correct.
     *
     * @var array<string, string|null>
     */
    private static array $cache = [];

    protected SettingModel $settingModel;

    public function __construct()
    {
        $this->settingModel = new SettingModel();
    }

    public function get(string $key, $default = null)
    {
        if (! array_key_exists($key, self::$cache)) {
            $row = $this->settingModel->findByKey($key);

            self::$cache[$key] = $row ? $row['setting_value'] : null;
        }

        return self::$cache[$key] ?? $default;
    }

    /**
     * @param string[] $keys
     * @return array<string, mixed> value per key; unsaved keys come back null
     */
    public function getMany(array $keys): array
    {
        $missing = array_values(array_filter(
            $keys,
            static fn (string $key): bool => ! array_key_exists($key, self::$cache),
        ));

        if ($missing !== []) {
            $rows = $this->settingModel->findByKeys($missing);

            foreach ($missing as $key) {
                self::$cache[$key] = $rows[$key]['setting_value'] ?? null;
            }
        }

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = self::$cache[$key];
        }

        return $values;
    }

    /**
     * Drops a key from the per-request memo so the next read re-queries.
     *
     * Called on every write. Invalidating rather than storing the new
     * value on purpose: the value written here is whatever the caller
     * passed, while a later read returns what the database actually holds
     * (always a string, post-cast). Re-reading keeps those identical for
     * the one-in-a-thousand request that saves and then reads back.
     */
    public function forget(string $key): void
    {
        unset(self::$cache[$key]);
    }

    public function set(string $key, $value, ?string $group = null): void
    {
        $this->forget($key);

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
