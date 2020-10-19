<?php

namespace App\Handlers;

class DomainHosting extends \App\Handlers\Base
{
    /**
     * The entitleable class for this handler.
     *
     * @return string
     */
    public static function entitleableClass(): string
    {
        return \App\Domain::class;
    }
}
