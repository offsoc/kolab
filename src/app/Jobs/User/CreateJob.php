<?php

namespace App\Jobs\User;

use App\EventLog;
use App\Jobs\UserJob;
use App\Plan;
use App\Support\Facades\DAV;
use App\Support\Facades\IMAP;
use App\Support\Facades\LDAP;
use App\User;

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
     * @throws \Exception
     */
    public function handle()
    {
        $this->logJobStart($this->userEmail);

        $user = $this->getUser();

        if (!$user) {
            return;
        }

        if ($user->role == User::ROLE_SERVICE) {
            return;
        }

        // TODO: this can be removed in favor of the above once we are sure the role is set everywhere.
        if ($user->email == \config('services.imap.admin_login')) {
            // Ignore Cyrus admin account
            return;
        }

        // sanity checks
        if ($user->isDeleted()) {
            $this->fail("User {$this->userId} is marked as deleted.");
            return;
        }

        if ($user->trashed()) {
            $this->fail("User {$this->userId} is actually deleted.");
            return;
        }

        if (!$user->hasSku('mailbox')) {
            return;
        }

        $withLdap = \config('app.with_ldap');

        // see if the domain is ready
        $domain = $user->domain();

        if (!$domain) {
            $this->fail("The domain for {$this->userId} does not exist.");
            return;
        }

        if ($domain->isDeleted()) {
            $this->fail("The domain for {$this->userId} is marked as deleted.");
            return;
        }

        if ($withLdap && !$domain->isLdapReady()) {
            $this->release(60);
            return;
        }

        if (\config('abuse.suspend_enabled') && !$user->isSuspended()) {
            $code = \Artisan::call("user:abuse-check {$this->userId}");
            if ($code == 2) {
                $msg = "Abuse check detected suspected spammer";
                \Log::info("{$msg}: {$this->userId} {$user->email}");
                EventLog::createFor($user, EventLog::TYPE_SUSPENDED, $msg);

                $user->status |= User::STATUS_SUSPENDED;
            }
        }

        if ($withLdap && !$user->isLdapReady()) {
            LDAP::createUser($user);

            $user->status |= User::STATUS_LDAP_READY;
            $user->save();
        }

        if (!$user->isImapReady()) {
            if (\config('app.with_imap')) {
                if (!IMAP::createUser($user)) {
                    throw new \Exception("Failed to create mailbox for user {$this->userId}.");
                }
            } else {
                if (!IMAP::verifyAccount($user->email)) {
                    $this->release(15);
                    return;
                }
            }

            $user->status |= User::STATUS_IMAP_READY;
        }

        // FIXME: Should we ignore exceptions on this operation or introduce DAV_READY status?
        DAV::initDefaultFolders($user);

        // Make user active in non-mandate mode only
        if (
            !($wallet = $user->wallet())
            || !($plan = $user->wallet()->plan())
            || $plan->mode != Plan::MODE_MANDATE
        ) {
            $user->status |= User::STATUS_ACTIVE;
        }

        $user->save();
    }
}
