<?php

namespace App\Support\Facades;

use Illuminate\Support\Facades\Facade;

class PGP extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'pgp';
    }
}
