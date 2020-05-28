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
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Entitlement::find($allegedly_unique)) {
                $entitlement->{$entitlement->getKeyName()} = $allegedly_unique;
                break;
            }
        }

        // can't dispatch job here because it'll fail serialization

        // Make sure the owner is at least a controller on the wallet
        $wallet = \App\Wallet::find($entitlement->wallet_id);

        if (!$wallet || !$wallet->owner) {
            return false;
        }

        $sku = \App\Sku::find($entitlement->sku_id);

        if (!$sku) {
            return false;
        }

        $result = $sku->handler_class::preReq($entitlement, $wallet->owner);

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
        $entitlement->entitleable->updated_at = Carbon::now();
        $entitlement->entitleable->save();

        $entitlement->createTransaction(\App\Transaction::ENTITLEMENT_CREATED);
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
        // Remove all configured 2FA methods from Roundcube database
        if ($entitlement->sku->title == '2fa') {
            // FIXME: Should that be an async job?
            $sf = new \App\Auth\SecondFactor($entitlement->entitleable);
            $sf->removeFactors();
        }

        $entitlement->entitleable->updated_at = Carbon::now();
        $entitlement->entitleable->save();

        $entitlement->createTransaction(\App\Transaction::ENTITLEMENT_DELETED);
    }
}
