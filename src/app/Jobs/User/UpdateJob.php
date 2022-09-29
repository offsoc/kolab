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

        if ($user->role) {
            // Admins/resellers don't reside in LDAP (for now)
            return;
        }

        if (\config('app.with_ldap') && $user->isLdapReady()) {
            \App\Backends\LDAP::updateUser($user);
        }

        if (\config('app.with_imap') && $user->isImapReady()) {
            if (!\App\Backends\IMAP::updateUser($user)) {
                throw new \Exception("Failed to update mailbox for user {$this->userId}.");
            }
        }
    }
}
