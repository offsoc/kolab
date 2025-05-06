<?php

namespace App\Console\Commands\Group;

use App\Console\ObjectListCommand;
use App\Group;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
