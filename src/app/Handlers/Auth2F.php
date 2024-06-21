<?php

namespace App\Handlers;

use App\Entitlement;

class Auth2F extends \App\Handlers\Base
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
     * Handle entitlement deletion event.
     */
    public static function entitlementDeleted(Entitlement $entitlement): void
    {
        // Remove all configured 2FA methods from Roundcube database
        if ($entitlement->entitleable && !$entitlement->entitleable->trashed()) {
            // TODO: This should be an async job
            $sf = new \App\Auth\SecondFactor($entitlement->entitleable);
            $sf->removeFactors();
        }
    }

    /**
     * SKU handler metadata.
     *
     * @param \App\Sku $sku The SKU object
     *
     * @return array
     */
    public static function metadata(\App\Sku $sku): array
    {
        $data = parent::metadata($sku);

        $data['forbidden'] = ['Activesync'];

        return $data;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     *
     * @return int
     */
    public static function priority(): int
    {
        return 60;
    }
}
