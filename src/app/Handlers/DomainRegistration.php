<?php

namespace App\Handlers;

use App\Sku;

class DomainRegistration
{
    public static function preReq($entitlement, $domain)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            return false;
        }

        return false;
    }
}
