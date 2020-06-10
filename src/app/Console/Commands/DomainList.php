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
    protected $signature = 'domain:list {--deleted}';

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
        if ($this->option('deleted')) {
            $domains = Domain::withTrashed()->orderBy('namespace');
        } else {
            $domains = Domain::orderBy('namespace');
        }

        $domains->each(
            function ($domain) {
                $msg = $domain->namespace;

                if ($domain->deleted_at) {
                    $msg .= " (deleted at {$domain->deleted_at})";
                }

                $this->info($msg);
            }
        );
    }
}
