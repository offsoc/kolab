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
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Wallet::find($allegedly_unique)) {
                $wallet->{$wallet->getKeyName()} = $allegedly_unique;
                break;
            }
        }
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
     * @return boolean|null
     */
    public function deleting(Wallet $wallet)
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
    }
}
