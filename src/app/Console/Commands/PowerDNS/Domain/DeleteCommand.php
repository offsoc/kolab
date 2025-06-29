<?php

namespace App\Console\Commands\PowerDNS\Domain;

use App\PowerDNS\Domain;
use Illuminate\Console\Command;

class DeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'powerdns:domain:delete {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete a PowerDNS domain';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('domain');

        $domain = Domain::where('name', $name)->first();

        if (!$domain) {
            return 1;
        }

        $domain->delete();
    }
}
