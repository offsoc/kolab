<?php

namespace App\Console\Commands\SharedFolder;

use App\Console\ObjectListCommand;
use App\SharedFolder;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = SharedFolder::class;
    protected $objectName = 'shared-folder';
    protected $objectTitle = 'name';
}
