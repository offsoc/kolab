<?php

namespace App\Handlers;

class Storage extends \App\Handlers\Base
{
    public const MAX_ITEMS = 100;
    public const ITEM_UNIT = 'GB';

    public static function entitleableClass(): string
    {
        return \App\User::class;
    }

    public static function preReq($entitlement, $object): bool
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        // TODO: The storage can not be modified to below what is already consumed.

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
        return 90;
    }
}
