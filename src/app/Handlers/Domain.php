<?php

namespace App\Handlers;

class Domain extends Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return \App\Domain::class;
    }
}
