<?php

namespace App\Handlers;

class Distlist extends Beta\Base
{
    /**
     * The entitleable class for this handler.
     *
     * @return string
     */
    public static function entitleableClass(): string
    {
        return \App\User::class;
    }

    /**
     * Check if the SKU is available to the user.
     *
     * @param \App\Sku  $sku  The SKU object
     * @param \App\User $user The user object
     *
     * @return bool
     */
    public static function isAvailable(\App\Sku $sku, \App\User $user): bool
    {
        // This SKU must be:
        // - already assigned, or active and a 'beta' entitlement must exist
        // - and this is a group account owner (custom domain)

        if (parent::isAvailable($sku, $user)) {
            return $user->wallet()->entitlements()
                ->where('entitleable_type', \App\Domain::class)->count() > 0;
        }

        return false;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     *
     * @return int
     */
    public static function priority(): int
    {
        return 10;
    }
}
