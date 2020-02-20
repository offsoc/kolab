<?php

namespace App\Handlers;

class Storage extends \App\Handlers\Base
{
    public static function entitleableClass()
    {
        return null;
    }

    public static function preReq($entitlement, $object)
    {
        // TODO: The storage can not be modified to below what is already consumed.

        return true;
    }
}
