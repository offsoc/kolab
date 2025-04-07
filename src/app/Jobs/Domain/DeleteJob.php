<?php

namespace App\Jobs\Domain;

use App\Jobs\DomainJob;

class DeleteJob extends DomainJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $domain = $this->getDomain();

        if (!$domain) {
            return;
        }

        // sanity checks
        if (!$domain->trashed()) {
            $this->fail("Domain {$this->domainId} is not deleted.");
            return;
        }

        if ($domain->isDeleted()) {
            $this->fail("Domain {$this->domainId} is already marked as deleted.");
            return;
        }

        if (\config('app.with_ldap') && $domain->isLdapReady()) {
            \App\Backends\LDAP::deleteDomain($domain);

            $domain->status ^= \App\Domain::STATUS_LDAP_READY;
        }

        $domain->status |= \App\Domain::STATUS_DELETED;
        $domain->save();
    }
}
