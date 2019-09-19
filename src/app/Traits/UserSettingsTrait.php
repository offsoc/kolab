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
    public function getSetting($key)
    {
        $settings = $this->_getCache();
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
    public function setSetting($key, $value)
    {
        $this->_storeSetting($key, $value);
        $this->_setCache();
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
    public function setSettings($data = [])
    {
        foreach ($data as $key => $value) {
            $this->_storeSetting($key, $value);
        }

        $this->_setCache();
    }

    private function _storeSetting($key, $value)
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

    private function _getCache()
    {
        if (Cache::has('user_settings_' . $this->id)) {
            return Cache::get('user_settings_' . $this->id);
        }

        return $this->_setCache();
    }

    private function _setCache()
    {
        if (Cache::has('user_settings_' . $this->id)) {
            Cache::forget('user_settings_' . $this->id);
        }

        $settings = $this->settings()->get();

        Cache::forever('user_settings_' . $this->id, $settings);

        return $this->_getCache();
    }
}
