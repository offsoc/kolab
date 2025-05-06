<?php

namespace App\Console\Commands\Scalpel\Group;

use App\Console\ObjectReadCommand;
use App\Group;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
