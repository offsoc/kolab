<?php
namespace App\Handlers;

use App\Sku;

class Domain
{
    public static function preReq($entitlement, $domain)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            return false;
        }

        return true;
    }
}
