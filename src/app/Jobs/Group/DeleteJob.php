<?php

namespace App\Jobs\Group;

use App\Jobs\GroupJob;

class DeleteJob extends GroupJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $group = $this->getGroup();

        // sanity checks
        if ($group->isDeleted()) {
            $this->fail(new \Exception("Group {$this->groupId} is already marked as deleted."));
            return;
        }

        \App\Backends\LDAP::deleteGroup($group);

        $group->status |= \App\Group::STATUS_DELETED;

        if ($group->isLdapReady()) {
            $group->status ^= \App\Group::STATUS_LDAP_READY;
        }

        $group->save();
    }
}
