<?php

namespace App\Handlers;

use App\Auth\SecondFactor;
use App\Entitlement;
use App\Jobs\User\UpdateJob;
use App\Sku;
use App\User;

class Auth2F extends Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return User::class;
    }

    /**
     * Handle entitlement creation event.
     */
    public static function entitlementCreated(Entitlement $entitlement): void
    {
        if (\config('app.with_ldap')) {
            UpdateJob::dispatch($entitlement->entitleable_id);
        }
    }

    /**
     * Handle entitlement deletion event.
     */
    public static function entitlementDeleted(Entitlement $entitlement): void
    {
        // Remove all configured 2FA methods from Roundcube database
        if ($entitlement->entitleable && !$entitlement->entitleable->trashed()) {
            // TODO: This should be an async job
            $sf = new SecondFactor($entitlement->entitleable);
            $sf->removeFactors();

            if (\config('app.with_ldap')) {
                UpdateJob::dispatch($entitlement->entitleable_id);
            }
        }
    }

    /**
     * SKU handler metadata.
     */
    public static function metadata(Sku $sku): array
    {
        $data = parent::metadata($sku);

        $data['forbidden'] = ['Activesync'];

        return $data;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     */
    public static function priority(): int
    {
        return 60;
    }
}
