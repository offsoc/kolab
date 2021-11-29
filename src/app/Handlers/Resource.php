<?php

namespace App\Handlers;

class Resource extends \App\Handlers\Base
{
    /**
     * The entitleable class for this handler.
     *
     * @return string
     */
    public static function entitleableClass(): string
    {
        return \App\Resource::class;
    }
}
