<?php

namespace App\Console\Commands\Scalpel\Entitlement;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Entitlement::class;
    protected $objectName = 'entitlement';
    protected $objectTitle = null;
}
