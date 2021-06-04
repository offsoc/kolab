<?php

namespace App\Console\Commands\Job;

use App\Console\Command;
use App\Domain;

class DomainCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'job:domaincreate {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Execute the DomainCreate job (again).";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = $this->getDomain($this->argument('domain'));

        if (!$domain) {
            return 1;
        }

        $job = new \App\Jobs\Domain\CreateJob($domain->id);
        $job->handle();
    }
}
