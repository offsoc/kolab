<?php

namespace App\Handlers;

use App\Sku;

class DomainHosting
{
    public static function preReq($entitlement, $domain)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            return false;
        }

        return false;
    }
}
