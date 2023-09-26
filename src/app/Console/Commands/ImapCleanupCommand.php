<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\Domain;

class ImapCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imap:cleanup {domain?} {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Cleanup IMAP ACL leftowers";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = $this->argument('domain');
        $dry_run = $this->option('dry-run');

        if (!$domain) {
            foreach (Domain::pluck('namespace')->all() as $domain) {
                // TODO: Execute this in parallel/background?
                \App\Backends\IMAP::aclCleanupDomain($domain, $dry_run);
            }

            return;
        }

        $domain = $this->getDomain($domain);

        if (!$domain) {
            $this->error("Domain not found.");
            return 1;
        }

        \App\Backends\IMAP::aclCleanupDomain($domain->namespace, $dry_run);
    }
}
