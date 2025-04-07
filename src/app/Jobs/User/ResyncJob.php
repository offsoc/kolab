<?php

namespace App\Jobs\User;

use App\Backends\IMAP;
use App\Backends\LDAP;
use App\Domain;
use App\Jobs\UserJob;
use App\User;

class ResyncJob extends UserJob
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

        $withLdap = \config('app.with_ldap');

        $userJob = \App\Jobs\User\UpdateJob::class;

        // Make sure the LDAP entry exists, fix that
        if ($withLdap && $user->isLdapReady()) {
            // Check (and fix) the custom domain state
            $domain = $user->domain();
            if (!$domain->isPublic() && !LDAP::getDomain($domain->namespace)) {
                $domain->status &= ~Domain::STATUS_LDAP_READY;
                $domain->save();

                \App\Jobs\Domain\CreateJob::dispatchSync($domain->id);
            }

            if (!LDAP::getUser($user->email)) {
                $user->status &= ~User::STATUS_LDAP_READY;
                $userJob = \App\Jobs\User\CreateJob::class;
            }
        }

        // Make sure the IMAP mailbox exists too
        if ($user->isImapReady()) {
            if (!IMAP::verifyAccount($user->email)) {
                $user->status &= ~User::STATUS_IMAP_READY;
                $userJob = \App\Jobs\User\CreateJob::class;
            }
        }

        $user->update();

        $userJob::dispatchSync($user->id);
    }
}
