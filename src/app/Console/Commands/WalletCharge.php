<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WalletCharge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:charge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge wallets';

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
        $wallets = \App\Wallet::all();

        foreach ($wallets as $wallet) {
            $charge = $wallet->chargeEntitlements();

            if ($charge > 0) {
                $this->info(
                    "Charged wallet {$wallet->id} for user {$wallet->owner->email} with {$charge}"
                );

                // Top-up the wallet if auto-payment enabled for the wallet
                \App\Jobs\WalletCharge::dispatch($wallet);
            }
        }
    }
}
