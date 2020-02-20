<?php

namespace App\Handlers;

use App\Sku;

class SharedFolder extends \App\Handlers\Base
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
