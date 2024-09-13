<?php

namespace App\Handlers;

use App\Entitlement;

class Groupware extends \App\Handlers\Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return \App\User::class;
    }

    /**
     * Handle entitlement creation event.
     */
    public static function entitlementCreated(Entitlement $entitlement): void
    {
        if (\config('app.with_ldap')) {
            \App\Jobs\User\UpdateJob::dispatch($entitlement->entitleable_id);
        }
    }

    /**
     * Handle entitlement deletion event.
     */
    public static function entitlementDeleted(Entitlement $entitlement): void
    {
        if (\config('app.with_ldap')) {
            \App\Jobs\User\UpdateJob::dispatch($entitlement->entitleable_id);
        }
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     */
    public static function priority(): int
    {
        return 80;
    }
}
