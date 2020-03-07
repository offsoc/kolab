<?php

namespace App\Handlers;

class Domain extends \App\Handlers\Base
{
    public static function entitleableClass()
    {
        return \App\Domain::class;
    }

    public static function preReq($entitlement, $domain)
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }
}
