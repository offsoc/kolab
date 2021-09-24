<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:create {domain} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a domain";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $namespace = \strtolower($this->argument('domain'));

        // must use withTrashed(), because unique constraint
        $domain = \App\Domain::withTrashed()->where('namespace', $namespace)->first();

        if ($domain && !$this->option('force')) {
            $this->error("Domain {$namespace} already exists.");
            return 1;
        }

        if ($domain) {
            if ($domain->trashed()) {
                // set the status back to new
                $domain->status = \App\Domain::STATUS_NEW;
                $domain->save();

                // remove existing entitlement
                $entitlement = \App\Entitlement::withTrashed()->where(
                    [
                        'entitleable_id' => $domain->id,
                        'entitleable_type' => \App\Domain::class
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
            $domain = \App\Domain::create(
                [
                    'namespace' => $namespace,
                    'type' => \App\Domain::TYPE_EXTERNAL,
                ]
            );

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
