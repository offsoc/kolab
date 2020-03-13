<?php

namespace App\Jobs;

use App\Backends\LDAP;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DomainUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $domain_id;

    /**
     * Create a new job instance.
     *
     * @param int $domain_id
     *
     * @return void
     */
    public function __construct($domain_id)
    {
        $this->domain_id = $domain_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $domain = \App\Domain::find($this->domain_id);

        LDAP::updateDomain($domain);
    }
}
