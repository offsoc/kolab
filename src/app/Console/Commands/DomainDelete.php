<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        $domain = \App\Domain::where('id', $this->argument('domain'))->first();

        if (!$domain) {
            return 1;
        }

        $domain->delete();
    }
}
