<?php

namespace App\Console\Commands\Domain;

use App\Console\Command;
use App\Entitlement;
use App\Domain;
use App\Sku;
use Illuminate\Support\Facades\Queue;

class SetWalletCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:set-wallet {domain} {wallet}';

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
            $this->error("Domain already assigned to a wallet: {$entitlement->wallet->id}.");
            return 1;
        }

        $sku = Sku::withObjectTenantContext($domain)->where('title', 'domain-hosting')->first();

        Queue::fake(); // ignore LDAP for now (note: adding entitlements updates the domain)

        Entitlement::create(
            [
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => 0,
                'fee' => 0,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class,
            ]
        );
    }
}
