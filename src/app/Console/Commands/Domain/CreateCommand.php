<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;
use App\Domain;
use App\Entitlement;
use App\Tenant;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:create {domain} {--force} {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a domain";

    /** @var bool Adds --tenant option handler */
    protected $withTenant = true;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        parent::handle();

        $namespace = \strtolower($this->argument('domain'));

        // must use withTrashed(), because unique constraint
        $domain = Domain::withTrashed()->where('namespace', $namespace)->first();

        if ($domain && !$this->option('force')) {
            $this->error("Domain {$namespace} already exists.");
            return 1;
        }

        if ($domain) {
            if ($domain->trashed()) {
                // set the status back to new
                $domain->status = Domain::STATUS_NEW;
                $domain->save();

                // remove existing entitlement
                $entitlement = Entitlement::withTrashed()->where(
                    [
                        'entitleable_id' => $domain->id,
                        'entitleable_type' => Domain::class
                    ]
                )->first();

                if ($entitlement) {
                    $entitlement->forceDelete();
                }

                // restore the domain to allow for the observer to handle the create job
                $domain->restore();

                $this->info(
                    sprintf(
                        "Domain %s with ID %d revived. Remember to assign it to a wallet with 'domain:set-wallet'",
                        $domain->namespace,
                        $domain->id
                    )
                );
            } else {
                $this->error("Domain {$namespace} not marked as deleted... examine more closely");
                return 1;
            }
        } else {
            $domain = new Domain();
            $domain->namespace = $namespace;
            $domain->type = Domain::TYPE_EXTERNAL;
            $domain->tenant_id = $this->tenantId;
            $domain->save();

            $this->info(
                sprintf(
                    "Domain %s created with ID %d. Remember to assign it to a wallet with 'domain:set-wallet'",
                    $domain->namespace,
                    $domain->id
                )
            );
        }
    }
}
