<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class EntitlementsCommand extends ObjectListCommand
{
    protected $objectClass = \App\Entitlement::class;
    protected $objectName = 'entitlement';
    protected $objectTitle = null;
}
