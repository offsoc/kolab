<?php

namespace App\Handlers;

use App\Quota;
use App\Sku;
use App\User;

class Storage
{
    public static function createDefaultEntitleable(User $user)
    {
        $quota = new Quota();
        $quota->user_id = $user->id;
        $quota->save();

        return $quota->id;
    }

    public static function entitleableClass()
    {
        return Quota::class;
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
