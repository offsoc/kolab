<?php

namespace App\Observers;

use App\GroupSetting;

class GroupSettingObserver
{
    /**
     * Handle the group setting "created" event.
     *
     * @param \App\GroupSetting $groupSetting Settings object
     *
     * @return void
     */
    public function created(GroupSetting $groupSetting)
    {
        $this->dispatchUpdateJob($groupSetting);
    }

    /**
     * Handle the group setting "updated" event.
     *
     * @param \App\GroupSetting $groupSetting Settings object
     *
     * @return void
     */
    public function updated(GroupSetting $groupSetting)
    {
        $this->dispatchUpdateJob($groupSetting);
    }

    /**
     * Handle the group setting "deleted" event.
     *
     * @param \App\GroupSetting $groupSetting Settings object
     *
     * @return void
     */
    public function deleted(GroupSetting $groupSetting)
    {
        $this->dispatchUpdateJob($groupSetting);
    }

    /**
     * Dispatch group update job (if needed).
     *
     * @param \App\GroupSetting $groupSetting Settings object
     */
    private function dispatchUpdateJob(GroupSetting $groupSetting): void
    {
        if ((\config('app.with_ldap') && in_array($groupSetting->key, \App\Backends\LDAP::GROUP_SETTINGS))
            || in_array($groupSetting->key, \App\Backends\IMAP::GROUP_SETTINGS)
        ) {
            \App\Jobs\Group\UpdateJob::dispatch($groupSetting->group_id);
        }
    }
}
