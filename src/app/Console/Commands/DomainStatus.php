<?php

namespace App\Console\Commands;

use App\Domain;
use Illuminate\Console\Command;

class DomainStatus extends Command
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
    protected $description = 'Display the status of a domain';

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
        $domain = Domain::where('namespace', $this->argument('domain'))->first();

        if (!$domain) {
            return 1;
        }

        $this->info("Found domain: {$domain->id}");

        $this->info($domain->status);
    }
}
