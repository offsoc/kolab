<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:restore {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore (undelete) a domain';

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

        if (!$domain->trashed()) {
            $this->error("The domain is not yet deleted.");
            return 1;
        }

        $owner = $domain->walletOwner();

        if (!$owner || $owner->trashed()) {
            $this->error("The domain owner is deleted.");
            return 1;
        }

        DB::beginTransaction();
        $domain->restore();
        DB::commit();
    }
}
