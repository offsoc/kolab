<?php

namespace App\Handlers;

class Resource extends \App\Handlers\Base
{
    public static function entitleableClass()
    {
        // TODO
    }

    public static function preReq($entitlement, $owner)
    {
        if (!$entitlement->sku->active) {
            \Log::error("Sku not active");
            return false;
        }

        return true;
    }
}
