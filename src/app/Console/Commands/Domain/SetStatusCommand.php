<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;
use Illuminate\Support\Facades\Queue;

class SetStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:set-status {domain} {status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Set a domain's status.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $domain = $this->getDomain($this->argument('domain'));

        if (!$domain) {
            $this->error("Domain not found.");
            return 1;
        }

        Queue::fake(); // ignore LDAP for now

        $domain->status = (int) $this->argument('status');
        $domain->save();

        $this->info($domain->status);
    }
}
