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

        // TODO: What should happen if the domain is not registered yet?
        //       Should we start a new job with some specified delay?
        //       Or we just give the user a button to start verification again?
    }
}
