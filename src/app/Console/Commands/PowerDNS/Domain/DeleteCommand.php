<?php

namespace App\Console\Commands\PowerDNS\Domain;

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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

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

        $domain->delete();
    }
}
