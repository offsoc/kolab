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
        // Get all wallets...
        $wallets = \App\Wallet::select('wallets.*')
            ->join('users', 'users.id', '=', 'wallets.user_id')
            // exclude deleted accounts
            ->whereNull('users.deleted_at')
            // exclude "inactive" accounts
            ->where('users.status', '&', \App\User::STATUS_IMAP_READY)
            // consider only these created 1 to 2 months ago
            ->where('users.created_at', '>', \now()->subMonthsNoOverflow(2))
            ->where('users.created_at', '<=', \now()->subMonthsNoOverflow(1))
            // skip wallets with the notification already sent
            ->whereNotExists(function ($query) {
                $query->from('wallet_settings')
                    ->where('wallet_settings.key', 'trial_end_notice')
                    ->whereColumn('wallet_settings.wallet_id', 'wallets.id');
            })
            // skip users that aren't account owners
            ->whereExists(function ($query) {
                $query->from('entitlements')
                    ->where('entitlements.entitleable_type', \App\User::class)
                    ->whereColumn('entitlements.entitleable_id', 'wallets.user_id')
                    ->whereColumn('entitlements.wallet_id', 'wallets.id');
            })
            ->cursor();

        foreach ($wallets as $wallet) {
            // Skip accounts with no trial period, or a period longer than a month
            $plan = $wallet->plan();
            if (!$plan || $plan->free_months != 1) {
                continue;
            }

            // Send the email asynchronously
            \App\Jobs\Mail\TrialEndJob::dispatch($wallet->owner);

            // Store the timestamp
            $wallet->setSetting('trial_end_notice', (string) \now());
        }
    }
}
