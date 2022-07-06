<?php

namespace App\Jobs\User;

use App\Jobs\UserJob;

class DeleteJob extends UserJob
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

        if ($user->role) {
            // Admins/resellers don't reside in LDAP (for now)
            return;
        }

        // sanity checks
        if ($user->isDeleted()) {
            $this->fail(new \Exception("User {$this->userId} is already marked as deleted."));
            return;
        }

        \App\Backends\LDAP::deleteUser($user);

        $user->status |= \App\User::STATUS_DELETED;

        if ($user->isLdapReady()) {
            $user->status ^= \App\User::STATUS_LDAP_READY;
        }

        if ($user->isImapReady()) {
            $user->status ^= \App\User::STATUS_IMAP_READY;
        }

        $user->save();
    }
}
