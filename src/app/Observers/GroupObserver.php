<?php

namespace App\Observers;

use App\EventLog;
use App\Group;
use App\Jobs\Group\CreateJob;
use App\Jobs\Group\DeleteJob;
use App\Jobs\Group\UpdateJob;

class GroupObserver
{
    /**
     * Handle the group "creating" event.
     *
     * @param Group $group The group
     */
    public function creating(Group $group): void
    {
        $group->status |= Group::STATUS_NEW;

        if (!isset($group->name) && isset($group->email)) {
            $group->name = explode('@', $group->email)[0];
        }
    }

    /**
     * Handle the group "created" event.
     *
     * @param Group $group The group
     */
    public function created(Group $group)
    {
        CreateJob::dispatch($group->id);
    }

    /**
     * Handle the group "deleted" event.
     *
     * @param Group $group The group
     */
    public function deleted(Group $group)
    {
        if ($group->isForceDeleting()) {
            // Remove EventLog records
            EventLog::where('object_id', $group->id)->where('object_type', Group::class)->delete();

            return;
        }

        DeleteJob::dispatch($group->id);
    }

    /**
     * Handle the group "updated" event.
     *
     * @param Group $group The group
     */
    public function updated(Group $group)
    {
        if (!$group->trashed()) {
            UpdateJob::dispatch($group->id);
        }
    }

    /**
     * Handle the group "restoring" event.
     *
     * @param Group $group The group
     */
    public function restoring(Group $group)
    {
        // Reset the status
        $group->status = Group::STATUS_NEW;

        // Note: $group->save() is invoked between 'restoring' and 'restored' events
    }

    /**
     * Handle the group "restored" event.
     *
     * @param Group $group The group
     */
    public function restored(Group $group)
    {
        CreateJob::dispatch($group->id);
    }
}
