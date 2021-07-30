<?php

namespace App\Traits;

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
     * @param string $key     Setting name
     * @param mixed  $default Default value, to be used if not found
     *
     * @return string|null Setting value
     */
    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    /**
     * Obtain the values for many settings in one go (for better performance).
     *
     * @param array $keys Setting names
     *
     * @return array Setting key=value hash, includes also requested but non-existing settings
     */
    public function getSettings(array $keys): array
    {
        $settings = [];

        foreach ($keys as $key) {
            $settings[$key] = null;
        }

        $this->settings()->whereIn('key', $keys)->get()
            ->each(function ($setting) use (&$settings) {
                $settings[$setting->key] = $setting->value;
            });

        return $settings;
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
    }

    /**
     * Create or update a setting.
     *
     * @param string      $key   Setting name
     * @param string|null $value The new value for the setting.
     *
     * @return void
     */
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
}
