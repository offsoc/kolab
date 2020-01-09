<?php

namespace App\Handlers;

use App\Sku;

class Domain
{
    public static function entitleableClass()
    {
        return \App\Domain::class;
    }

    public static function preReq($entitlement, $domain)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }
}
