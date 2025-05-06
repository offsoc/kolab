<?php

namespace App\Handlers;

use App\Entitlement;
use App\Jobs\User\UpdateJob;
use App\User;

class Groupware extends Base
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
        if (\config('app.with_ldap')) {
            UpdateJob::dispatch($entitlement->entitleable_id);
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
