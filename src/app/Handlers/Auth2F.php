<?php

namespace App\Handlers;

class Auth2F extends \App\Handlers\Base
{
    public static function entitleableClass()
    {
        return \App\User::class;
    }

    public static function preReq($entitlement, $object)
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }

    public static function priority(): int
    {
        return 60;
    }
}
