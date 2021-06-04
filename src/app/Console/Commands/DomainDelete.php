<?php

namespace App\Console\Commands;

use App\Console\Command;

class DomainDelete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:delete {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a domain';

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

        $domain->delete();
    }
}
