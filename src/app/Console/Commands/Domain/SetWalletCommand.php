<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;
use App\Package;
use Illuminate\Support\Facades\Queue;

class SetWalletCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:set-wallet {domain} {wallet} {--package=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Assign a domain to a wallet.";

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

        $wallet = $this->getWallet($this->argument('wallet'));

        if (!$wallet) {
            $this->error("Wallet not found.");
            return 1;
        }

        if ($entitlement = $domain->entitlements()->first()) {
            $this->error("Domain already assigned to a wallet: {$entitlement->wallet_id}.");
            return 1;
        }

        if ($p = $this->option('package')) {
            $package = Package::withObjectTenantContext($domain)->find($p);

            if (!$package) {
                $package = Package::withObjectTenantContext($domain)->where('title', $p)->first();
            }
        } else {
            $package = Package::withObjectTenantContext($domain)->where('title', 'domain-hosting')->first();
        }

        if (!$package) {
            $this->error("Package not found.");
            return 1;
        }

        Queue::fake(); // ignore LDAP for now (note: adding entitlements updates the domain)

        // Assign package the same as we do in DomainsController when a new domain is created
        $domain->assignPackageAndWallet($package, $wallet);
    }
}
