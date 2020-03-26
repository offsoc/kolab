<?php

namespace App\Handlers;

class Activesync extends \App\Handlers\Base
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
}
