<?php

namespace App\Jobs\Domain;

use App\Jobs\DomainJob;

class VerifyJob extends DomainJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $domain = $this->getDomain();

        $domain->verify();
    }
}
