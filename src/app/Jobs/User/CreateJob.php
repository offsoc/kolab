<?php

namespace App\Jobs\User;

use App\Jobs\UserJob;

/**
 * Create the \App\User in LDAP.
 *
 * Throws exceptions for the following reasons:
 *
 *   * The user is marked as deleted (`$user->isDeleted()`), or
 *   * the user is actually deleted (`$user->deleted_at`), or
 *   * the user is already marked as ready in LDAP (`$user->isLdapReady()`).
 *
 */
class CreateJob extends UserJob
{
    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Exception
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
            $this->fail(new \Exception("User {$this->userId} is marked as deleted."));
            return;
        }

        if ($user->trashed()) {
            $this->fail(new \Exception("User {$this->userId} is actually deleted."));
            return;
        }

        $withLdap = \config('app.with_ldap');

        // see if the domain is ready
        $domain = $user->domain();

        if (!$domain) {
            $this->fail(new \Exception("The domain for {$this->userId} does not exist."));
            return;
        }

        if ($domain->isDeleted()) {
            $this->fail(new \Exception("The domain for {$this->userId} is marked as deleted."));
            return;
        }

        if ($withLdap && !$domain->isLdapReady()) {
            $this->release(60);
            return;
        }

        if ($withLdap && !$user->isLdapReady()) {
            \App\Backends\LDAP::createUser($user);

            $user->status |= \App\User::STATUS_LDAP_READY;
            $user->save();
        }

        if (!$user->isImapReady()) {
            if (!\App\Backends\IMAP::createUser($user)) {
                throw new \Exception("Failed to create mailbox for user {$this->userId}.");
            }

            $user->status |= \App\User::STATUS_IMAP_READY;
        }

        $user->status |= \App\User::STATUS_ACTIVE;
        $user->save();
    }
}
