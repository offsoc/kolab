<?php

namespace App\Console\Commands\Resource;

use App\Console\ObjectListCommand;
use App\Resource;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = Resource::class;
    protected $objectName = 'resource';
    protected $objectTitle = 'name';
}
