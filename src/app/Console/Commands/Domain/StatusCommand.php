<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;

class StatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:status {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Display the status of a domain";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = $this->getDomain($this->argument('domain'), true);

        if (!$domain) {
            $this->error("Domain not found.");
            return 1;
        }

        $this->info("Status ({$domain->status}): " . $domain->statusText());
    }
}
