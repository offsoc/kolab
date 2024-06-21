<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class ResourcesCommand extends ObjectListCommand
{
    protected $objectClass = \App\Resource::class;
    protected $objectName = 'resource';
    protected $objectTitle = 'name';
}
