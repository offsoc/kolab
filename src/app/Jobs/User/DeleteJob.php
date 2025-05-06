<?php

namespace App\Jobs\User;

use App\Jobs\UserJob;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use App\Support\Facades\Roundcube;
use App\User;

class DeleteJob extends UserJob
{
    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->logJobStart($this->userEmail);

        $user = $this->getUser();

        if (!$user) {
            return;
        }

        if ($user->role) {
            // Admins/resellers don't reside in LDAP (for now)
            return;
        }

        if (!$user->trashed()) {
            $this->fail("User {$this->userId} is not deleted.");
            return;
        }

        // sanity checks
        if ($user->isDeleted()) {
            $this->fail("User {$this->userId} is already marked as deleted.");
            return;
        }

        if (\config('app.with_ldap') && $user->isLdapReady()) {
            LDAP::deleteUser($user);

            $user->status ^= User::STATUS_LDAP_READY;
            $user->save();
        }

        if ($user->isImapReady()) {
            if (\config('app.with_imap')) {
                if (!IMAP::deleteUser($user)) {
                    throw new \Exception("Failed to delete mailbox for user {$this->userId}.");
                }
            }

            $user->status ^= User::STATUS_IMAP_READY;
        }

        if (\config('database.connections.roundcube')) {
            Roundcube::deleteUser($user->email);
        }

        $user->status |= User::STATUS_DELETED;
        $user->save();
    }
}
