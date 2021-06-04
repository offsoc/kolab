<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WalletBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:balances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the balance on wallets';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $wallets = \App\Wallet::select('wallets.*')
            ->join('users', 'users.id', '=', 'wallets.user_id')
            ->withEnvTenant('users')
            ->all();

        $wallets->each(
            function ($wallet) {
                if ($wallet->balance == 0) {
                    return;
                }

                $user = $wallet->owner;

                if (!$user) {
                    return;
                }

                $this->info(
                    sprintf(
                        "%s: %8s (account: %s/%s (%s))",
                        $wallet->id,
                        $wallet->balance,
                        "https://kolabnow.com/cockpit/admin/accounts/show",
                        $user->id,
                        $user->email
                    )
                );
            }
        );
    }
}
