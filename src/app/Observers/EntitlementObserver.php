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
        // anything's free for 14 days
        if ($entitlement->created_at >= Carbon::now()->subDays(14)) {
            return;
        }

        $cost = 0;

        // get the discount rate applied to the wallet.
        $discount = $entitlement->wallet->getDiscountRate();

        // just in case this had not been billed yet, ever
        $diffInMonths = $entitlement->updated_at->diffInMonths(Carbon::now());
        $cost += (int) ($entitlement->cost * $discount * $diffInMonths);

        // this moves the hypothetical updated at forward to however many months past the original
        $updatedAt = $entitlement->updated_at->copy()->addMonthsWithoutOverflow($diffInMonths);

        // now we have the diff in days since the last "billed" period end.
        // This may be an entitlement paid up until February 28th, 2020, with today being March
        // 12th 2020. Calculating the costs for the entitlement is based on the daily price for the
        // past month -- i.e. $price/29 in the case at hand -- times the number of (full) days in
        // between the period end and now.
        //
        // a) The number of days left in the past month, 1
        // b) The cost divided by the number of days in the past month, for example, 555/29,
        // c) a) + Todays day-of-month, 12, so 13.
        //

        $diffInDays = $updatedAt->diffInDays(Carbon::now());

        $dayOfThisMonth = Carbon::now()->day;

        // days in the month for the month prior to this one.
        // the price per day is based on the number of days left in the last month
        $daysInLastMonth = \App\Utils::daysInLastMonth();

        $pricePerDay = (float)$entitlement->cost / $daysInLastMonth;

        $cost += (int) (round($pricePerDay * $diffInDays, 0));

        if ($cost == 0) {
            return;
        }

        $entitlement->wallet->debit($cost);
    }
}
