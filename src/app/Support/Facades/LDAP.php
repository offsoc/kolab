<?php

namespace App\Support\Facades;

use Illuminate\Support\Facades\Facade;

class LDAP extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ldap';
    }
}
