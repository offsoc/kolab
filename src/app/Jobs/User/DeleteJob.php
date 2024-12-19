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
            $this->fail(new \Exception("User {$this->userId} is not deleted."));
            return;
        }

        // sanity checks
        if ($user->isDeleted()) {
            $this->fail(new \Exception("User {$this->userId} is already marked as deleted."));
            return;
        }

        if (\config('app.with_ldap') && $user->isLdapReady()) {
            \App\Backends\LDAP::deleteUser($user);

            $user->status ^= \App\User::STATUS_LDAP_READY;
            $user->save();
        }

        if ($user->isImapReady()) {
            if (\config('app.with_imap')) {
                if (!\App\Backends\IMAP::deleteUser($user)) {
                    throw new \Exception("Failed to delete mailbox for user {$this->userId}.");
                }
            }

            $user->status ^= \App\User::STATUS_IMAP_READY;
        }

        if (\config('database.connections.roundcube')) {
            \App\Backends\Roundcube::deleteUser($user->email);
        }

        $user->status |= \App\User::STATUS_DELETED;
        $user->save();
    }
}
