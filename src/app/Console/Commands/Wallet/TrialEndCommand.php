<?php

namespace App\Console\Commands\Wallet;

use App\Console\Command;

class TrialEndCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:trial-end';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify wallet (account) owners about an end of the trial period.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Get all wallets, excluding deleted/inactive accounts
        // created precisely a month ago
        $wallets = \App\Wallet::select('wallets.*')
            ->join('users', 'users.id', '=', 'wallets.user_id')
            ->leftJoin('wallet_settings', function ($join) {
                $join->on('wallet_settings.wallet_id', '=', 'wallets.id')
                    ->where('wallet_settings.key', 'trial_end_notice');
            })
            ->withEnvTenantContext('users')
            ->whereNull('users.deleted_at')
            ->where('users.status', '&', \App\User::STATUS_IMAP_READY)
            ->where('users.created_at', '>', \now()->subMonthsNoOverflow(2))
            ->whereNull('wallet_settings.value')
            ->cursor();

        foreach ($wallets as $wallet) {
            // Send the email asynchronously
            \App\Jobs\TrialEndEmail::dispatch($wallet->owner);

            // Store the timestamp
            $wallet->setSetting('trial_end_notice', (string) \now());
        }
    }
}
