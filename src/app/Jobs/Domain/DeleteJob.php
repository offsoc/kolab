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

        // sanity checks
        if ($domain->isDeleted()) {
            $this->fail(new \Exception("Domain {$this->domainId} is already marked as deleted."));
            return;
        }

        \App\Backends\LDAP::deleteDomain($domain);

        $domain->status |= \App\Domain::STATUS_DELETED;

        if ($domain->isLdapReady()) {
            $domain->status ^= \App\Domain::STATUS_LDAP_READY;
        }

        $domain->save();
    }
}
