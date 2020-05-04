<?php

namespace App\Handlers;

class Groupware extends \App\Handlers\Base
{
    public static function entitleableClass(): string
    {
        return \App\User::class;
    }

    public static function preReq($entitlement, $user): bool
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }

    /**
     * The priority that specifies the order of SKUs in UI.
     * Higher number means higher on the list.
     *
     * @return int
     */
    public static function priority(): int
    {
        return 80;
    }
}
