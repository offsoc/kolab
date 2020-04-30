<?php

namespace App\Traits\OpenVidu;

use App\OpenVidu\RoomSetting;
use Illuminate\Support\Facades\Cache;

trait RoomSettingsTrait
{
    /**
     * Obtain the value for a setting.
     *
     * Example Usage:
     *
     * ```php
     * $room = Room::firstOrCreate(['id' => 'some-legible-name']);
     * $locked = $room->getSetting('locked');
     * ```
     *
     * @param string $key Lookup key
     *
     * @return string|null
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
     * Create or update a setting.
     *
     * Example Usage:
     *
     * ```php
     * $room = Room::firstOrCreate(['id' => 'some-legible-name']);
     * $room->setSetting('locked', true);
     * ```
     *
     * @param string      $key   Setting name
     * @param string|null $value The new value for the setting.
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
     * $room = Room::firstOrCreate(['id' => 'some-legible-name']);
     * $room->setSettings(['locked' => true]);
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

    private function storeSetting(string $key, $value): void
    {
        if ($value === null || $value === '') {
            if ($setting = RoomSetting::where(['room_id' => $this->id, 'key' => $key])->first()) {
                $setting->delete();
            }
        } else {
            RoomSetting::updateOrCreate(
                ['room_id' => $this->id, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    private function getCache()
    {
        if (Cache::has('room_settings_' . $this->id)) {
            return Cache::get('room_settings_' . $this->id);
        }

        return $this->setCache();
    }

    private function setCache()
    {
        if (Cache::has('room_settings_' . $this->id)) {
            Cache::forget('room_settings_' . $this->id);
        }

        $cached = [];
        foreach ($this->settings()->get() as $entry) {
            if ($entry->value !== null && $entry->value !== '') {
                $cached[$entry->key] = $entry->value;
            }
        }

        Cache::forever('room_settings_' . $this->id, $cached);

        return $this->getCache();
    }
}
