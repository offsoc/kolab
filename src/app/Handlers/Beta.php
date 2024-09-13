<?php

namespace App\Handlers;

use App\Entitlement;

class Beta extends \App\Handlers\Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return \App\User::class;
    }

    /**
     * Prerequisites for the Entitlement to be applied to the object.
     */
    public static function preReq(Entitlement $entitlement, $object): bool
    {
        // We allow inactive "beta" Sku to be assigned

        if (self::entitleableClass() !== $entitlement->entitleable_type) {
            \Log::error("Entitleable class mismatch");
            return false;
        }

        return true;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     */
    public static function priority(): int
    {
        // Just above all other beta SKUs, please
        return 10;
    }
}
