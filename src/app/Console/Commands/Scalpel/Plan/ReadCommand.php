<?php

namespace App\Console\Commands\Scalpel\Plan;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Plan::class;
    protected $objectName = 'plan';
    protected $objectTitle = 'title';
}
