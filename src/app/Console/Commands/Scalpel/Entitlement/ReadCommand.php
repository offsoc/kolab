<?php

namespace App\Console\Commands\Scalpel\Entitlement;

use App\Console\ObjectReadCommand;
use App\Entitlement;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Entitlement::class;
    protected $objectName = 'entitlement';
    protected $objectTitle;
}
