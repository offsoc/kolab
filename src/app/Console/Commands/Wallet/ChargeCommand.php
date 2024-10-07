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
    protected $signature = 'wallet:charge {--topup : Only top-up wallets} {--dry-run} {wallet?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Charge wallets, and trigger a topup on charged wallets.';

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
        } elseif ($this->option('topup')) {
            // Find wallets that need to be topped up
            $wallets = \App\Wallet::select('wallets.id')
                ->join('users', 'users.id', '=', 'wallets.user_id')
                ->join('wallet_settings', function (\Illuminate\Database\Query\JoinClause $join) {
                    $join->on('wallet_settings.wallet_id', '=', 'wallets.id')
                        ->where('wallet_settings.key', '=', 'mandate_balance');
                })
                ->whereNull('users.deleted_at')
                ->whereRaw('wallets.balance < (wallet_settings.value * 100)')
                ->whereNot(
                    'users.status',
                    '&',
                    \App\User::STATUS_DEGRADED | \App\User::STATUS_SUSPENDED | \App\User::STATUS_DELETED
                )
                ->cursor();
        } else {
            // Get all wallets, excluding deleted accounts
            $wallets = \App\Wallet::select('wallets.id')
                ->join('users', 'users.id', '=', 'wallets.user_id')
                ->whereNull('users.deleted_at')
                ->cursor();
        }

        foreach ($wallets as $wallet) {
            if ($this->option('dry-run')) {
                $this->info($wallet->id);
            } else {
                if ($this->option('topup')) {
                    \App\Jobs\WalletCharge::dispatch($wallet->id);
                } else {
                    \App\Jobs\WalletCheck::dispatch($wallet->id);
                }
            }
        }
    }
}
