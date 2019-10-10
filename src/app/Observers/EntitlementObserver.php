<?php

namespace App\Observers;

use App\Entitlement;

/**
 * This is an observer for the Entitlement model definition.
 */
class EntitlementObserver
{
    /**
     * Handle the "creating" event on an Entitlement.
     *
     * Ensures that the entry uses a custom ID (uuid).
     *
     * Ensures that the {@link \App\Wallet} to which it is to be billed is owned or controlled by
     * the {@link \App\User} entitled.
     *
     * @param Entitlement $entitlement The entitlement being created.
     *
     * @return void
     */
    public function creating(Entitlement $entitlement)
    {
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Entitlement::find($allegedly_unique)) {
                $entitlement->{$entitlement->getKeyName()} = $allegedly_unique;
                break;
            }
        }

        // can't dispatch job here because it'll fail serialization

        // Make sure the owner is at least a controller on the wallet
        $owner = \App\User::find($entitlement->owner_id);
        $wallet = \App\Wallet::find($entitlement->wallet_id);

        if (!$owner) {
            return false;
        }

        if (!$wallet) {
            return false;
        }

        if (!$wallet->owner() == $owner) {
            if (!$wallet->controllers->contains($owner)) {
                return false;
            }
        }

        $sku = \App\Sku::find($entitlement->sku_id);

        if (!$sku) {
            return false;
        }

        $result = $sku->handler_class::preReq($entitlement, $owner);

        if (!$result) {
            return false;
        }

        // TODO: Handle the first free unit here?

        // TODO: Execute the Sku handler class or function?

        $wallet->debit($sku->cost);
    }
}
