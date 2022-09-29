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

        if (!$group) {
            return;
        }

        // sanity checks
        if ($group->isDeleted()) {
            $this->fail(new \Exception("Group {$this->groupId} is already marked as deleted."));
            return;
        }

        if (\config('app.with_ldap') && $group->isLdapReady()) {
            \App\Backends\LDAP::deleteGroup($group);

            $group->status ^= \App\Group::STATUS_LDAP_READY;
        }
/*
        if (!\App\Backends\IMAP::deleteGroup($group)) {
            throw new \Exception("Failed to delete group {$this->groupId} from IMAP.");
        }
*/
        $group->status |= \App\Group::STATUS_DELETED;
        $group->save();
    }
}
