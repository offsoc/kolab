<?php

namespace App\Console\Commands\SharedFolder;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\SharedFolder::class;
    protected $objectName = 'shared-folder';
    protected $objectTitle = 'name';
}
