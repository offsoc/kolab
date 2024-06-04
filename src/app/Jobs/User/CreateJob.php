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
 */
class CreateJob extends UserJob
{
    /** @var int Enable waiting for a user record to exist */
    protected $waitForUser = 5;

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

        if ($user->email == \config('imap.admin_login')) {
            // Ignore Cyrus admin account
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

        if (\config('abuse.suspend_enabled') && !$user->isSuspended()) {
            $code = \Artisan::call("user:abuse-check {$this->userId}");
            if ($code == 2) {
                \Log::info("Suspending user due to suspected abuse: {$this->userId} {$user->email}");
                \App\EventLog::createFor($user, \App\EventLog::TYPE_SUSPENDED, "Suspected spammer");

                $user->status |= \App\User::STATUS_SUSPENDED;
            }
        }

        if ($withLdap && !$user->isLdapReady()) {
            \App\Backends\LDAP::createUser($user);

            $user->status |= \App\User::STATUS_LDAP_READY;
            $user->save();
        }

        if (!$user->isImapReady()) {
            if (\config('app.with_imap')) {
                if (!\App\Backends\IMAP::createUser($user)) {
                    throw new \Exception("Failed to create mailbox for user {$this->userId}.");
                }
            } else {
                if (!\App\Backends\IMAP::verifyAccount($user->email)) {
                    $this->release(15);
                    return;
                }
            }

            $user->status |= \App\User::STATUS_IMAP_READY;
        }

        // Make user active in non-mandate mode only
        if (
            !($wallet = $user->wallet())
            || !($plan = $user->wallet()->plan())
            || $plan->mode != \App\Plan::MODE_MANDATE
        ) {
            $user->status |= \App\User::STATUS_ACTIVE;
        }

        $user->save();
    }
}
