<?php

namespace App\Observers;

use App\Wallet;

/**
 * This is an observer for the Wallet model definition.
 */
class WalletObserver
{
    /**
     * Ensure the wallet ID is a custom ID (uuid).
     *
     * @param Wallet $wallet
     *
     * @return void
     */
    public function creating(Wallet $wallet)
    {
        $wallet->currency = \config('app.currency');
    }

    /**
     * Handle the wallet "deleting" event.
     *
     * Ensures that a wallet with a non-zero balance can not be deleted.
     *
     * Ensures that the wallet being deleted is not the last wallet for the user.
     *
     * Ensures that no entitlements are being billed to the wallet currently.
     *
     * @param Wallet $wallet The wallet being deleted.
     *
     * @return bool
     */
    public function deleting(Wallet $wallet): bool
    {
        // can't delete a wallet that has any balance on it (positive and negative).
        if ($wallet->balance != 0.00) {
            return false;
        }

        if (!$wallet->owner) {
            throw new \Exception("Wallet: " . var_export($wallet, true));
        }

        // can't remove the last wallet for the owner.
        if ($wallet->owner->wallets()->count() <= 1) {
            return false;
        }

        // can't remove a wallet that has billable entitlements attached.
        if ($wallet->entitlements()->count() > 0) {
            return false;
        }
/*
        // can't remove a wallet that has payments attached.
        if ($wallet->payments()->count() > 0) {
            return false;
        }
*/

        return true;
    }

    /**
     * Handle the wallet "updated" event.
     *
     * @param \App\Wallet $wallet The wallet.
     *
     * @return void
     */
    public function updated(Wallet $wallet)
    {
        $negative_since = $wallet->getSetting('balance_negative_since');

        if ($wallet->balance < 0) {
            if (!$negative_since) {
                $now = \Carbon\Carbon::now()->toDateTimeString();
                $wallet->setSetting('balance_negative_since', $now);
            }
        } elseif ($negative_since) {
            $wallet->setSettings([
                    'balance_negative_since' => null,
                    'balance_warning_initial' => null,
                    'balance_warning_reminder' => null,
                    'balance_warning_suspended' => null,
                    'balance_warning_before_delete' => null,
            ]);

            // FIXME: Since we use account degradation, should we leave suspended state untouched?

            // Un-suspend and un-degrade the account owner
            if ($wallet->owner) {
                $wallet->owner->unsuspend();
                $wallet->owner->undegrade();
            }

            // Un-suspend domains/users
            foreach ($wallet->entitlements as $entitlement) {
                if (method_exists($entitlement->entitleable_type, 'unsuspend')) {
                    $entitlement->entitleable->unsuspend();
                }
            }
        }
    }
}
