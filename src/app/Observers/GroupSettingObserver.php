<?php

namespace App\Observers;

use App\GroupSetting;
use App\Jobs\Group\UpdateJob;

class GroupSettingObserver
{
    /**
     * Handle the group setting "created" event.
     *
     * @param GroupSetting $groupSetting Settings object
     */
    public function created(GroupSetting $groupSetting)
    {
        $this->dispatchUpdateJob($groupSetting);
    }

    /**
     * Handle the group setting "updated" event.
     *
     * @param GroupSetting $groupSetting Settings object
     */
    public function updated(GroupSetting $groupSetting)
    {
        $this->dispatchUpdateJob($groupSetting);
    }

    /**
     * Handle the group setting "deleted" event.
     *
     * @param GroupSetting $groupSetting Settings object
     */
    public function deleted(GroupSetting $groupSetting)
    {
        $this->dispatchUpdateJob($groupSetting);
    }

    /**
     * Dispatch group update job (if needed).
     *
     * @param GroupSetting $groupSetting Settings object
     */
    private function dispatchUpdateJob(GroupSetting $groupSetting): void
    {
        if ($groupSetting->isBackendSetting()) {
            UpdateJob::dispatch($groupSetting->group_id);
        }
    }
}
