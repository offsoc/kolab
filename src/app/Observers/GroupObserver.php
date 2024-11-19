<?php

namespace App\Observers;

use App\Group;
use Illuminate\Support\Facades\DB;

class GroupObserver
{
    /**
     * Handle the group "created" event.
     *
     * @param \App\Group $group The group
     *
     * @return void
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
     * @param \App\Group $group The group
     *
     * @return void
     */
    public function created(Group $group)
    {
        \App\Jobs\Group\CreateJob::dispatch($group->id);
    }

    /**
     * Handle the group "deleted" event.
     *
     * @param \App\Group $group The group
     *
     * @return void
     */
    public function deleted(Group $group)
    {
        if ($group->isForceDeleting()) {
            // Remove EventLog records
            \App\EventLog::where('object_id', $group->id)->where('object_type', Group::class)->delete();

            return;
        }

        \App\Jobs\Group\DeleteJob::dispatch($group->id);
    }

    /**
     * Handle the group "updated" event.
     *
     * @param \App\Group $group The group
     *
     * @return void
     */
    public function updated(Group $group)
    {
        if (!$group->trashed()) {
            \App\Jobs\Group\UpdateJob::dispatch($group->id);
        }
    }

    /**
     * Handle the group "restoring" event.
     *
     * @param \App\Group $group The group
     *
     * @return void
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
     * @param \App\Group $group The group
     *
     * @return void
     */
    public function restored(Group $group)
    {
        \App\Jobs\Group\CreateJob::dispatch($group->id);
    }
}
