<?php

namespace App\Observers;

use App\User;
use App\Wallet;
use Carbon\Carbon;

/**
 * This is an observer for the Wallet model definition.
 */
class WalletObserver
{
    /**
     * Ensure the wallet ID is a custom ID (uuid).
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
     * @param Wallet $wallet the wallet being deleted
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
     * @param Wallet $wallet the wallet
     */
    public function updated(Wallet $wallet)
    {
        $negative_since = $wallet->getSetting('balance_negative_since');

        if ($wallet->balance < 0) {
            if (!$negative_since) {
                $now = Carbon::now()->toDateTimeString();
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
                if (
                    method_exists($entitlement->entitleable_type, 'unsuspend')
                    && !empty($entitlement->entitleable)
                ) {
                    $entitlement->entitleable->unsuspend();
                }
            }
        }

        // Remove RESTRICTED flag from the wallet owner and all users in the wallet
        if ($wallet->balance > $wallet->getOriginal('balance') && $wallet->owner && $wallet->owner->isRestricted()) {
            $wallet->owner->unrestrict(true);
        }
    }
}
