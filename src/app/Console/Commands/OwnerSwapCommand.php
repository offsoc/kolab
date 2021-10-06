<?php

namespace App\Console\Commands;

use App\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class OwnerSwapCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'owner:swap {current-user} {target-user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Swap account owner (to another user)';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->argument('current-user') == $this->argument('target-user')) {
            $this->error('Users cannot be the same.');
            return 1;
        }

        $user = $this->getUser($this->argument('current-user'));

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        $target = $this->getUser($this->argument('target-user'));

        if (!$target) {
            $this->error('User not found.');
            return 1;
        }

        $wallet = $user->wallets->first();
        $target_wallet = $target->wallets->first();

        if ($wallet->id != $target->wallet()->id) {
            $this->error('The target user does not belong to the same account.');
            return 1;
        }

        Queue::fake();

        DB::beginTransaction();

        // Switch wallet for existing entitlements
        $wallet->entitlements()->withTrashed()->update(['wallet_id' => $target_wallet->id]);

        // Update target user created_at
        $dt = \now()->subMonthsWithoutOverflow(1);
        if ($target->created_at >= $dt) {
            $target->created_at = $dt;
            $target->save();
        }

        // Migrate wallet properties
        $target_wallet->balance = $wallet->balance;
        $target_wallet->currency = $wallet->currency;
        $target_wallet->save();

        $wallet->balance = 0;
        $wallet->save();

        // Migrate wallet settings
        $settings = $wallet->settings()->get();

        \App\WalletSetting::where('wallet_id', $wallet->id)->delete();
        \App\WalletSetting::where('wallet_id', $target_wallet->id)->delete();

        foreach ($settings as $setting) {
            $target_wallet->setSetting($setting->key, $setting->value);
        }

        DB::commit();

        // Update mollie/stripe customer email (which point to the old wallet id)
        $this->updatePaymentCustomer($target_wallet);
    }

    /**
     * Update user/wallet metadata at payment provider
     *
     * @param \App\Wallet $wallet The wallet
     */
    private function updatePaymentCustomer(\App\Wallet $wallet): void
    {
        if ($mollie_id = $wallet->getSetting('mollie_id')) {
            mollie()->customers()->update($mollie_id, [
                    'name'  => $wallet->owner->name(),
                    'email' => $wallet->id . '@private.' . \config('app.domain'),
            ]);
        }

        // TODO: Stripe
    }
}
