<?php

namespace App\Handlers;

use App\Domain;

class DomainRegistration extends Base
{
    /**
     * The entitleable class for this handler.
     */
    public static function entitleableClass(): string
    {
        return Domain::class;
    }
}
