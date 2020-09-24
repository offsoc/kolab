<?php

namespace App\Console\Commands;

use App\Domain;
use App\Entitlement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class DomainAdd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:add {domain} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a domain.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $namespace = \strtolower($this->argument('domain'));

        // must use withTrashed(), because unique constraint
        $domain = Domain::withTrashed()->where('namespace', $namespace)->first();

        if ($domain && !$this->option('force')) {
            $this->error("Domain {$namespace} already exists.");
            return 1;
        }

        Queue::fake(); // ignore LDAP for now

        if ($domain) {
            if ($domain->deleted_at) {
                // revive domain
                $domain->deleted_at = null;
                $domain->status = 0;
                $domain->save();

                // remove existing entitlement
                $entitlement = Entitlement::withTrashed()->where(
                    [
                        'entitleable_id' => $domain->id,
                        'entitleable_type' => \App\Domain::class
                    ]
                )->first();

                if ($entitlement) {
                    $entitlement->forceDelete();
                }
            } else {
                $this->error("Domain {$namespace} not marked as deleted... examine more closely");
                return 1;
            }
        } else {
            $domain = Domain::create([
                    'namespace' => $namespace,
                    'type' => Domain::TYPE_EXTERNAL,
            ]);
        }

        $this->info($domain->id);
    }
}
