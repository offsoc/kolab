<?php

namespace App\Observers;

use App\Backends\LDAP;
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
        if (in_array($groupSetting->key, LDAP::GROUP_SETTINGS)) {
            \App\Jobs\Group\UpdateJob::dispatch($groupSetting->group_id);
        }
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
        if (in_array($groupSetting->key, LDAP::GROUP_SETTINGS)) {
            \App\Jobs\Group\UpdateJob::dispatch($groupSetting->group_id);
        }
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
        if (in_array($groupSetting->key, LDAP::GROUP_SETTINGS)) {
            \App\Jobs\Group\UpdateJob::dispatch($groupSetting->group_id);
        }
    }
}
