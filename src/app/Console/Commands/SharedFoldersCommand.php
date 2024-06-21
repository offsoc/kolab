<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class SharedFoldersCommand extends ObjectListCommand
{
    protected $objectClass = \App\SharedFolder::class;
    protected $objectName = 'shared-folder';
    protected $objectTitle = 'name';
}
