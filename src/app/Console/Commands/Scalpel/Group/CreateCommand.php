<?php

namespace App\Console\Commands\Scalpel\Group;

use App\Console\ObjectCreateCommand;
use App\Group;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
