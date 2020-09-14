<?php

namespace App\Console\Commands;

use App\Entitlement;
use App\Domain;
use App\Sku;
use App\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class DomainSetWallet extends Command
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
        $domain = Domain::where('namespace', $this->argument('domain'))->first();

        if (!$domain) {
            $this->error("Domain not found.");
            return 1;
        }

        $wallet = Wallet::find($this->argument('wallet'));

        if (!$wallet) {
            $this->error("Wallet not found.");
            return 1;
        }

        if ($domain->entitlement) {
            $this->error("Domain already assigned to a wallet: {$domain->entitlement->wallet->id}.");
            return 1;
        }

        $sku = Sku::where('title', 'domain-hosting')->first();

        Queue::fake(); // ignore LDAP for now (note: adding entitlements updates the domain)

        Entitlement::create(
            [
                'wallet_id' => $wallet->id,
                'sku_id' => $sku->id,
                'cost' => 0,
                'entitleable_id' => $domain->id,
                'entitleable_type' => Domain::class,
            ]
        );
    }
}
