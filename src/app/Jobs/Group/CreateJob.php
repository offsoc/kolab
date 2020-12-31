<?php

namespace App\Jobs\Group;

use App\Jobs\GroupJob;

class CreateJob extends GroupJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $group = $this->getGroup();

        if (!$group->isLdapReady()) {
            \App\Backends\LDAP::createGroup($group);

            $group->status |= \App\Group::STATUS_LDAP_READY;
            $group->save();
        }
    }
}
