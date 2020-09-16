<?php

namespace App\Console\Commands\Job;

use App\Domain;
use Illuminate\Console\Command;

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
        $domain = Domain::where('namespace', $this->argument('domain'))->first();

        if (!$domain) {
            return 1;
        }

        $job = new \App\Jobs\DomainCreate($domain);
        $job->handle();
    }
}
