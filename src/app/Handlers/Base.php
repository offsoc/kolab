<?php

namespace App\Handlers;

abstract class Base
{
    /**
     * The entitleable class for this handler.
     *
     * @return mixed
     */
    public static function entitleableClass()
    {
        //
    }

    /**
     * Prerequisites for the Entitlement to be applied to the object.
     *
     * @param \App\Entitlement $entitlement
     * @param mixed $object
     *
     * @return bool
     */
    public static function preReq($entitlement, $object)
    {
        //
    }
}
