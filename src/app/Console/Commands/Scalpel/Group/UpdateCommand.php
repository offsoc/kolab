<?php

namespace App\Console\Commands\Scalpel\Group;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
