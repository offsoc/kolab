<?php

namespace App\Jobs\Group;

use App\Jobs\GroupJob;

class UpdateJob extends GroupJob
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
            $this->delete();
            return;
        }

        \App\Backends\LDAP::updateGroup($group);
    }
}
