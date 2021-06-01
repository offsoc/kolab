<?php

namespace App\Console\Commands;

use App\Wallet;
use Illuminate\Console\Command;

class WalletCharge extends Command
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
        if ($wallet = $this->argument('wallet')) {
            // Find specified wallet by ID
            $wallet = Wallet::find($wallet);

            if (!$wallet || !$wallet->owner || $wallet->owner->tenant_id != \config('app.tenant_id')) {
                return 1;
            }

            $wallets = [$wallet];
        } else {
            // Get all wallets, excluding deleted accounts
            $wallets = Wallet::select('wallets.*')
                ->join('users', 'users.id', '=', 'wallets.user_id')
                ->withEnvTenant('users')
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
                // Check the account balance, send notifications, suspend, delete
                \App\Jobs\WalletCheck::dispatch($wallet);
            }
        }
    }
}
