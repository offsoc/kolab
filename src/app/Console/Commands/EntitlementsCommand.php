<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;
use App\Entitlement;

class EntitlementsCommand extends ObjectListCommand
{
    protected $objectClass = Entitlement::class;
    protected $objectName = 'entitlement';
    protected $objectTitle;
}
