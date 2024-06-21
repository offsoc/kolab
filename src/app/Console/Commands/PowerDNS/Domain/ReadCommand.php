<?php

namespace App\Console\Commands\PowerDNS\Domain;

use Illuminate\Console\Command;

class ReadCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'powerdns:domain:read {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read a PowerDNS domain';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->argument('domain');

        $domain = \App\PowerDNS\Domain::where('name', $name)->first();

        if (!$domain) {
            return 1;
        }

        foreach ($domain->records as $record) {
            $this->info($record->toString());
        }
    }
}
