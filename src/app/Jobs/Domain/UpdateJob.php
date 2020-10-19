<?php

namespace App\Jobs\Domain;

use App\Jobs\DomainJob;

class UpdateJob extends DomainJob
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
            $this->delete();
            return;
        }

        \App\Backends\LDAP::updateDomain($domain);
    }
}
