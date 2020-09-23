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

    public function deleting(Entitlement $entitlement)
    {
        // Start calculating the costs for the consumption of this entitlement if the
        // existing consumption spans >= 14 days.
        //
        // Effect is that anything's free for the first 14 days
        if ($entitlement->created_at >= Carbon::now()->subDays(14)) {
            return;
        }

        $owner = $entitlement->wallet->owner;

        // Determine if we're still within the free first month
        $freeMonthEnds = $owner->created_at->copy()->addMonthsWithoutOverflow(1);

        if ($freeMonthEnds >= Carbon::now()) {
            return;
        }

        $cost = 0;
        $now = Carbon::now();

        // get the discount rate applied to the wallet.
        $discount = $entitlement->wallet->getDiscountRate();

        // just in case this had not been billed yet, ever
        $diffInMonths = $entitlement->updated_at->diffInMonths($now);
        $cost += (int) ($entitlement->cost * $discount * $diffInMonths);

        // this moves the hypothetical updated at forward to however many months past the original
        $updatedAt = $entitlement->updated_at->copy()->addMonthsWithoutOverflow($diffInMonths);

        // now we have the diff in days since the last "billed" period end.
        // This may be an entitlement paid up until February 28th, 2020, with today being March
        // 12th 2020. Calculating the costs for the entitlement is based on the daily price

        // the price per day is based on the number of days in the last month
        // or the current month if the period does not overlap with the previous month
        // FIXME: This really should be simplified to $daysInMonth=30

        $diffInDays = $updatedAt->diffInDays($now);

        if ($now->day >= $diffInDays) {
            $daysInMonth = $now->daysInMonth;
        } else {
            $daysInMonth = \App\Utils::daysInLastMonth();
        }

        $pricePerDay = $entitlement->cost / $daysInMonth;

        $cost += (int) (round($pricePerDay * $discount * $diffInDays, 0));

        if ($cost == 0) {
            return;
        }

        $entitlement->wallet->debit($cost);
    }
}
