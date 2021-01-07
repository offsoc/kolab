<?php

namespace App\Console\Commands\Scalpel\Group;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
