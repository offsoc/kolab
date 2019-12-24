<?php

namespace App\Handlers;

use App\Sku;

class Groupware
{
    public static function preReq($entitlement, $user)
    {
        if (!Sku::find($entitlement->sku_id)->active) {
            return false;
        }

        return true;
    }
}
