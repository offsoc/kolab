<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class ChargeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:charge {wallet?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge wallets';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($wallet = $this->argument('wallet')) {
            // Find specified wallet by ID
            $wallet = $this->getWallet($wallet);

            if (!$wallet) {
                $this->error("Wallet not found.");
                return 1;
            }

            if (!$wallet->owner) {
                $this->error("Wallet's owner is deleted.");
                return 1;
            }

            $wallets = [$wallet];
        } else {
            // Get all wallets, excluding deleted accounts
            $wallets = \App\Wallet::select('wallets.*')
                ->join('users', 'users.id', '=', 'wallets.user_id')
                ->withEnvTenantContext('users')
                ->whereNull('users.deleted_at')
                ->cursor();
        }

        foreach ($wallets as $wallet) {
            $charge = $wallet->chargeEntitlements();

            if ($charge > 0) {
                $this->info(
                    "Charged wallet {$wallet->id} for user {$wallet->owner->email} with {$charge}"
                );

                // Top-up the wallet if auto-payment enabled for the wallet
                \App\Jobs\WalletCharge::dispatch($wallet);
            }

            if ($wallet->balance < 0) {
                // Check the account balance, send notifications, (suspend, delete,) degrade
                // Also sends reminders to the degraded account owners
                \App\Jobs\WalletCheck::dispatch($wallet);
            }
        }
    }
}
