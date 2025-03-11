<?php

namespace App\Observers;

use App\Entitlement;
use Carbon\Carbon;

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
     * @return bool
     */
    public function creating(Entitlement $entitlement): bool
    {
        // can't dispatch job here because it'll fail serialization

        // Make sure the owner is at least a controller on the wallet
        $wallet = \App\Wallet::find($entitlement->wallet_id);

        if (!$wallet || !$wallet->owner) {
            return false;
        }

        if (empty($entitlement->sku)) {
            return false;
        }

        $result = $entitlement->sku->handler_class::preReq($entitlement, $wallet->owner);

        if (!$result) {
            return false;
        }

        return true;
    }

    /**
     * Handle the entitlement "created" event.
     *
     * @param \App\Entitlement $entitlement The entitlement.
     *
     * @return void
     */
    public function created(Entitlement $entitlement)
    {
        $entitlement->createTransaction(\App\Transaction::ENTITLEMENT_CREATED);

        $entitlement->sku->handler_class::entitlementCreated($entitlement);
    }

    /**
     * Handle the entitlement "deleted" event.
     *
     * @param \App\Entitlement $entitlement The entitlement.
     *
     * @return void
     */
    public function deleted(Entitlement $entitlement)
    {
        $entitlement->createTransaction(\App\Transaction::ENTITLEMENT_DELETED);

        $entitlement->sku->handler_class::entitlementDeleted($entitlement);
    }

    /**
     * Handle the entitlement "deleting" event.
     *
     * @param \App\Entitlement $entitlement The entitlement.
     *
     * @return void
     */
    public function deleting(Entitlement $entitlement)
    {
        // Disable updating of updated_at column on delete, we need it unchanged to later
        // charge the wallet for the uncharged period before the entitlement has been deleted
        $entitlement->timestamps = false;
    }
}
