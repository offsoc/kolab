<?php

namespace App\Handlers;

class Groupware extends \App\Handlers\Base
{
    public static function entitleableClass()
    {
        return \App\User::class;
    }

    public static function preReq($entitlement, $user)
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }
}
