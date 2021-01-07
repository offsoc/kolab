<?php

namespace App\Console\Commands\Scalpel\Group;

use App\Console\ObjectCreateCommand;

class CreateCommand extends ObjectCreateCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
