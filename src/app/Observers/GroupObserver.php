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
        $group->status |= Group::STATUS_NEW | Group::STATUS_ACTIVE;

        $group->tenant_id = \config('app.tenant_id');
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
     * Handle the group "deleting" event.
     *
     * @param \App\Group $group The group
     *
     * @return void
     */
    public function deleting(Group $group)
    {
        // Entitlements do not have referential integrity on the entitled object, so this is our
        // way of doing an onDelete('cascade') without the foreign key.
        \App\Entitlement::where('entitleable_id', $group->id)
            ->where('entitleable_type', Group::class)
            ->delete();
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
        \App\Jobs\Group\UpdateJob::dispatch($group->id);
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
        //
    }

    /**
     * Handle the group "force deleting" event.
     *
     * @param \App\Group $group The group
     *
     * @return void
     */
    public function forceDeleted(Group $group)
    {
        // A group can be force-deleted separately from the owner
        // we have to force-delete entitlements
        \App\Entitlement::where('entitleable_id', $group->id)
            ->where('entitleable_type', Group::class)
            ->forceDelete();
    }
}
