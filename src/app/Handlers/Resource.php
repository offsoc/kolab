<?php
namespace App\Handlers;

use App\Sku;

class Resource
{
    public static function preReq($entitlement, $owner)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            return false;
        }

        return true;
    }
}
