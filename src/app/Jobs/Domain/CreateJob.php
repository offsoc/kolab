<?php

namespace App\Jobs\Domain;

use App\Jobs\DomainJob;

class CreateJob extends DomainJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $domain = $this->getDomain();

        if (!$domain->isLdapReady()) {
            \App\Backends\LDAP::createDomain($domain);

            $domain->status |= \App\Domain::STATUS_LDAP_READY;
            $domain->save();

            \App\Jobs\Domain\VerifyJob::dispatch($domain->id);
        }
    }
}
