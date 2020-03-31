<?php

namespace App\Console\Commands;

use App\Domain;
use Illuminate\Console\Command;

class DomainList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List domains';

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
        $domains = Domain::withTrashed()->orderBy('namespace')->each(
            function ($domain) {
                $this->info($domain->namespace);
            }
        );
    }
}
