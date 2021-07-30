<?php

namespace App\Console\Commands;

use App\Console\Command;

class WalletExpected extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:expected {--user=} {--non-zero}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show expected charges to wallets (for user)';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->option('user')) {
            $user = $this->getUser($this->option('user'));

            if (!$user) {
                return 1;
            }

            $wallets = $user->wallets;
        } else {
            $wallets = \App\Wallet::select('wallets.*')
                ->join('users', 'users.id', '=', 'wallets.user_id')
                ->withEnvTenantContext('users')
                ->all();
        }

        foreach ($wallets as $wallet) {
            $charge = 0;
            $expected = $wallet->expectedCharges();

            if (!$wallet->owner) {
                \Log::debug("{$wallet->id} has no owner: {$wallet->user_id}");
                continue;
            }

            if ($this->option('non-zero') && $expected < 1) {
                continue;
            }

            $this->info(
                sprintf(
                    "expect charging wallet %s for user %s with %d",
                    $wallet->id,
                    $wallet->owner->email,
                    $expected
                )
            );
        }
    }
}
