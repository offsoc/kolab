<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\Domain;

class DomainUnsuspend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:unsuspend {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a domain suspension';

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

        $this->info("Found domain {$domain->id}");

        $domain->unsuspend();
    }
}
