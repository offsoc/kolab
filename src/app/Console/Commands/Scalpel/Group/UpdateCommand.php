<?php

namespace App\Console\Commands\Scalpel\Group;

use App\Console\ObjectUpdateCommand;
use App\Group;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
