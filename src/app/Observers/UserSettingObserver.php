<?php

namespace App\Observers;

use App\UserSetting;

class UserSettingObserver
{
    /**
     * Handle the user setting "created" event.
     *
     * @param  \App\UserSetting  $userSetting
     * @return void
     */
    public function created(UserSetting $userSetting)
    {
        //
    }

    /**
     * Handle the user setting "updated" event.
     *
     * @param  \App\UserSetting  $userSetting
     * @return void
     */
    public function updated(UserSetting $userSetting)
    {
        \App\Jobs\UserUpdate::dispatch($userSetting->user);
    }

    /**
     * Handle the user setting "deleted" event.
     *
     * @param  \App\UserSetting  $userSetting
     * @return void
     */
    public function deleted(UserSetting $userSetting)
    {
        //
    }

    /**
     * Handle the user setting "restored" event.
     *
     * @param  \App\UserSetting  $userSetting
     * @return void
     */
    public function restored(UserSetting $userSetting)
    {
        //
    }

    /**
     * Handle the user setting "force deleted" event.
     *
     * @param  \App\UserSetting  $userSetting
     * @return void
     */
    public function forceDeleted(UserSetting $userSetting)
    {
        //
    }
}
