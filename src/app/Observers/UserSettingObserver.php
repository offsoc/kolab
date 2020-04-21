<?php

namespace App\Observers;

use App\Backends\LDAP;
use App\UserSetting;

class UserSettingObserver
{
    /**
     * Handle the user setting "created" event.
     *
     * @param \App\UserSetting $userSetting Settings object
     *
     * @return void
     */
    public function created(UserSetting $userSetting)
    {
        if (in_array($userSetting->key, LDAP::USER_SETTINGS)) {
            \App\Jobs\UserUpdate::dispatch($userSetting->user);
        }
    }

    /**
     * Handle the user setting "updated" event.
     *
     * @param \App\UserSetting $userSetting Settings object
     *
     * @return void
     */
    public function updated(UserSetting $userSetting)
    {
        if (in_array($userSetting->key, LDAP::USER_SETTINGS)) {
            \App\Jobs\UserUpdate::dispatch($userSetting->user);
        }
    }

    /**
     * Handle the user setting "deleted" event.
     *
     * @param \App\UserSetting $userSetting Settings object
     *
     * @return void
     */
    public function deleted(UserSetting $userSetting)
    {
        if (in_array($userSetting->key, LDAP::USER_SETTINGS)) {
            \App\Jobs\UserUpdate::dispatch($userSetting->user);
        }
    }
}
