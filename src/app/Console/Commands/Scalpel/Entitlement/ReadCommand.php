<?php

namespace App\Console\Commands\Scalpel\Entitlement;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Entitlement::class;
    protected $objectName = 'entitlement';
    protected $objectTitle = null;
}
