<?php

namespace App\Handlers\Beta;

class Meet extends Base
{
    /**
     * The entitleable class for this handler.
     *
     * @return string
     */
    public static function entitleableClass(): string
    {
        // Note: We connot just inherit from the parent because
        // we use static:: there.
        return \App\User::class;
    }
}
