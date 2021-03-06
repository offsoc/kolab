<?php

namespace App\Jobs\User;

use App\Jobs\UserJob;

class UpdateJob extends UserJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = $this->getUser();

        if (!$user) {
            return;
        }

        if (!$user->isLdapReady()) {
            $this->delete();
            return;
        }

        \App\Backends\LDAP::updateUser($user);
    }
}
