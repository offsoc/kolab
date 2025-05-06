<?php

namespace App\Console\Commands\Scalpel\Plan;

use App\Console\ObjectReadCommand;
use App\Plan;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Plan::class;
    protected $objectName = 'plan';
    protected $objectTitle = 'title';
}
