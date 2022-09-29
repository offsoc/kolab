<?php

namespace App\Observers;

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
        $this->dispatchUpdateJob($userSetting);
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
        $this->dispatchUpdateJob($userSetting);
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
        $this->dispatchUpdateJob($userSetting);
    }

    /**
     * Dispatch the user update job (if needed).
     *
     * @param \App\UserSetting $userSetting Settings object
     */
    private function dispatchUpdateJob(UserSetting $userSetting): void
    {
        if ((\config('app.with_ldap') && in_array($userSetting->key, \App\Backends\LDAP::USER_SETTINGS))
            || in_array($userSetting->key, \App\Backends\IMAP::USER_SETTINGS)
        ) {
            \App\Jobs\User\UpdateJob::dispatch($userSetting->user_id);
        }
    }
}
