<?php

namespace App\Observers;

use App\Discount;

/**
 * This is an observer for the Discount model definition.
 */
class DiscountObserver
{
    /**
     * Ensure the discount ID is a custom ID (uuid).
     *
     * @param \App\Discount $discount The discount object
     *
     * @return void
     */
    public function creating(Discount $discount): void
    {
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Discount::find($allegedly_unique)) {
                $discount->{$discount->getKeyName()} = $allegedly_unique;
                break;
            }
        }

        $discount->tenant_id = \config('app.tenant_id');
    }
}
