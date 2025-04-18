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

        if (!$domain) {
            return;
        }

        if (!\config('app.with_ldap') || !$domain->isLdapReady()) {
            $this->delete();
            return;
        }

        \App\Support\Facades\LDAP::updateDomain($domain);
    }
}
