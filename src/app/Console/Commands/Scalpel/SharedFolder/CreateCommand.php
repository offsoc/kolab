<?php

namespace App\Console\Commands\Scalpel\SharedFolder;

use App\Console\ObjectCreateCommand;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\SharedFolder::class;
    protected $objectName = 'folder';
    protected $objectTitle = 'email';
}
