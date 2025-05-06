<?php

namespace App\Jobs\Group;

use App\Group;
use App\Jobs\GroupJob;
use App\Support\Facades\LDAP;

class DeleteJob extends GroupJob
{
    /**
     * Execute the job.
     */
    public function handle()
    {
        $group = $this->getGroup();

        if (!$group) {
            return;
        }

        // sanity checks
        if (!$group->trashed()) {
            $this->fail("Group {$this->groupId} is not deleted.");
            return;
        }

        if ($group->isDeleted()) {
            $this->fail("Group {$this->groupId} is already marked as deleted.");
            return;
        }

        if (\config('app.with_ldap') && $group->isLdapReady()) {
            LDAP::deleteGroup($group);

            $group->status ^= Group::STATUS_LDAP_READY;
        }
        /*
                if (\config('app.with_imap') && $group->isImapReady()) {
                    if (!\App\Support\Facades\IMAP::deleteGroup($group)) {
                        throw new \Exception("Failed to delete group {$this->groupId} from IMAP.");
                    }

                    $group->status ^= \App\Group::STATUS_IMAP_READY;
                }
        */
        $group->status |= Group::STATUS_DELETED;
        $group->save();
    }
}
