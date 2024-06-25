<?php

namespace App\Console\Commands\Resource;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\Resource::class;
    protected $objectName = 'resource';
    protected $objectTitle = 'name';
}
