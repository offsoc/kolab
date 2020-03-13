<?php

namespace App\Handlers;

class Storage extends \App\Handlers\Base
{
    public const MAX_ITEMS = 100;
    public const ITEM_UNIT = 'GB';

    public static function entitleableClass()
    {
        return \App\User::class;
    }

    public static function preReq($entitlement, $object)
    {
        // TODO: The storage can not be modified to below what is already consumed.

        return true;
    }
}
