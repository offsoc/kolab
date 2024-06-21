<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class GroupsCommand extends ObjectListCommand
{
    protected $objectClass = \App\Group::class;
    protected $objectName = 'group';
    protected $objectTitle = 'email';
}
