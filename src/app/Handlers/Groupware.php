<?php

namespace App\Handlers;

use App\Sku;

class Groupware
{
    public static function entitleableClass()
    {
        return \App\User::class;
    }

    public static function preReq($entitlement, $user)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }
}
