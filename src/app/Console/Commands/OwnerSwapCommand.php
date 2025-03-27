<?php

namespace App\Console\Commands;

use App\Console\Command;
use App\User;
use App\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mollie\Laravel\Facades\Mollie;

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

        $this->migrateWallet($wallet, $target_wallet);
        $this->migrateUser($user, $target);

        DB::commit();

        // Update mollie/stripe customer email (which point to the old wallet id)
        $this->updatePaymentCustomer($target_wallet);
    }

    /**
     * Move settings from one user to another
     */
    private function migrateUser(User $source, User $target): void
    {
        // Update target user's created_at timestamp to the source user's created_at.
        // This is needed because we use this date when charging entitlements,
        // i.e. the first month is free.
        $dt = \now()->subMonthsWithoutOverflow(1);
        if ($target->created_at > $dt && $target->created_at > $source->created_at) {
            $target->created_at = $source->created_at;
            $target->save();
        }

        // Move plan_id setting
        if ($plan_id = $source->getSetting('plan_id')) {
            $target->setSetting('plan_id', $plan_id);
            $source->removeSetting('plan_id');
        }
    }

    /**
     * Move entitlements, settings and state from one wallet to another
     */
    private function migrateWallet(Wallet $source, Wallet $target): void
    {
        // Switch wallet for existing entitlements
        $source->entitlements()->withTrashed()->update(['wallet_id' => $target->id]);

        // Migrate wallet properties
        $target->balance = $source->balance;
        $target->currency = $source->currency;
        $target->save();

        $source->balance = 0;
        $source->save();

        // Migrate wallet settings
        $settings = $source->settings()->get();

        $source->settings()->delete();
        $target->settings()->delete();

        foreach ($settings as $setting) {
            $target->setSetting($setting->key, $setting->value);
        }
    }

    /**
     * Update user/wallet metadata at payment provider
     *
     * @param \App\Wallet $wallet The wallet
     */
    private function updatePaymentCustomer(\App\Wallet $wallet): void
    {
        if ($mollie_id = $wallet->getSetting('mollie_id')) {
            Mollie::api()->customers->update($mollie_id, [
                    'name'  => $wallet->owner->name(),
                    'email' => $wallet->id . '@private.' . \config('app.domain'),
            ]);
        }

        // TODO: Stripe
    }
}
