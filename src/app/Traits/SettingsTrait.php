<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait SettingsTrait
{
    /**
     * Obtain the value for a setting.
     *
     * Example Usage:
     *
     * ```php
     * $user = User::firstOrCreate(['email' => 'some@other.erg']);
     * $locale = $user->getSetting('locale');
     * ```
     *
     * @param string $key Setting name
     *
     * @return string|null Setting value
     */
    public function getSetting(string $key)
    {
        $settings = $this->getCache();

        if (!array_key_exists($key, $settings)) {
            return null;
        }

        $value = $settings[$key];

        return empty($value) ? null : $value;
    }

    /**
     * Remove a setting.
     *
     * Example Usage:
     *
     * ```php
     * $user = User::firstOrCreate(['email' => 'some@other.erg']);
     * $user->removeSetting('locale');
     * ```
     *
     * @param string $key Setting name
     *
     * @return void
     */
    public function removeSetting(string $key): void
    {
        $this->setSetting($key, null);
    }

    /**
     * Create or update a setting.
     *
     * Example Usage:
     *
     * ```php
     * $user = User::firstOrCreate(['email' => 'some@other.erg']);
     * $user->setSetting('locale', 'en');
     * ```
     *
     * @param string      $key   Setting name
     * @param string|null $value The new value for the setting.
     *
     * @return void
     */
    public function setSetting(string $key, $value): void
    {
        $this->storeSetting($key, $value);
        $this->setCache();
    }

    /**
     * Create or update multiple settings in one fell swoop.
     *
     * Example Usage:
     *
     * ```php
     * $user = User::firstOrCreate(['email' => 'some@other.erg']);
     * $user->setSettings(['locale', 'en', 'country' => 'GB']);
     * ```
     *
     * @param array $data An associative array of key value pairs.
     *
     * @return void
     */
    public function setSettings(array $data = []): void
    {
        foreach ($data as $key => $value) {
            $this->storeSetting($key, $value);
        }

        $this->setCache();
    }

    private function storeSetting(string $key, $value): void
    {
        if ($value === null || $value === '') {
            // Note: We're selecting the record first, so observers can act
            if ($setting = $this->settings()->where('key', $key)->first()) {
                $setting->delete();
            }
        } else {
            $this->settings()->updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    private function getCache()
    {
        $model = \strtolower(get_class($this));

        if (Cache::has("{$model}_settings_{$this->id}")) {
            return Cache::get("{$model}_settings_{$this->id}");
        }

        return $this->setCache();
    }

    private function setCache()
    {
        $model = \strtolower(get_class($this));

        if (Cache::has("{$model}_settings_{$this->id}")) {
            Cache::forget("{$model}_settings_{$this->id}");
        }

        $cached = [];
        foreach ($this->settings()->get() as $entry) {
            if ($entry->value !== null && $entry->value !== '') {
                $cached[$entry->key] = $entry->value;
            }
        }

        Cache::forever("{$model}_settings_{$this->id}", $cached);

        return $this->getCache();
    }
}
