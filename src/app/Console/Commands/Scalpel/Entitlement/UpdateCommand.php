<?php

namespace App\Console\Commands\Scalpel\Entitlement;

use App\Console\ObjectUpdateCommand;
use App\Entitlement;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Entitlement::class;
    protected $objectName = 'entitlement';
    protected $objectTitle;
}
