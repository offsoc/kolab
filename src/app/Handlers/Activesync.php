<?php

namespace App\Handlers;

class Activesync extends \App\Handlers\Base
{
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

        return true;
    }

    public static function priority(): int
    {
        return 70;
    }
}
