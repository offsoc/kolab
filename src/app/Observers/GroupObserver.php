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
     * Handle the group "restoring" event.
     *
     * @param \App\Group $group The group
     *
     * @return void
     */
    public function restoring(Group $group)
    {
        // Make sure it's not DELETED/LDAP_READY/SUSPENDED anymore
        if ($group->isDeleted()) {
            $group->status ^= Group::STATUS_DELETED;
        }
        if ($group->isLdapReady()) {
            $group->status ^= Group::STATUS_LDAP_READY;
        }
        if ($group->isSuspended()) {
            $group->status ^= Group::STATUS_SUSPENDED;
        }

        $group->status |= Group::STATUS_ACTIVE;

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
