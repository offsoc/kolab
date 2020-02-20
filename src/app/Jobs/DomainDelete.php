<?php

namespace App\Jobs;

use App\Backends\LDAP;
use App\Domain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;

class DomainDelete implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $domain;

    public $tries = 5;

    /** @var bool Delete the job if its models no longer exist. */
    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param Domain $domain The domain to delete.
     *
     * @return void
     */
    public function __construct(Domain $domain)
    {
        $this->domain = $domain;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        LDAP::deleteDomain($this->domain);
    }
}
