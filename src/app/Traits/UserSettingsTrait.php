<?php

namespace App\Traits;

use App\UserSetting;
use Illuminate\Support\Facades\Cache;

trait UserSettingsTrait
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
     * @param string $key Lookup key
     *
     * @return string
     */
    public function getSetting(string $key)
    {
        $settings = $this->getCache();
        $value = array_get($settings, $key);

        return ($value !== '') ? $value : null;
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
     * @param string $key   Setting name
     * @param string $value The new value for the setting.
     *
     * @return void
     */
    public function setSetting(string $key, $value)
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
    public function setSettings(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->storeSetting($key, $value);
        }

        $this->setCache();
    }

    private function storeSetting(string $key, $value)
    {
        $record = UserSetting::where(['user_id' => $this->id, 'key' => $key])->first();

        if ($record) {
            $record->value = $value;
            $record->save();
        } else {
            $data = new UserSetting(['key' => $key, 'value' => $value]);
            $this->settings()->save($data);
        }
    }

    private function getCache()
    {
        if (Cache::has('user_settings_' . $this->id)) {
            return Cache::get('user_settings_' . $this->id);
        }

        return $this->setCache();
    }

    private function setCache()
    {
        if (Cache::has('user_settings_' . $this->id)) {
            Cache::forget('user_settings_' . $this->id);
        }

        $cached = [];
        foreach ($this->settings()->get() as $entry) {
            $cached[$entry->key] = $entry->value;
        }

        Cache::forever('user_settings_' . $this->id, $cached);

        return $this->getCache();
    }
}
