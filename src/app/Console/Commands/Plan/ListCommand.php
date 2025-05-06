<?php

namespace App\Console\Commands\Plan;

use App\Console\ObjectListCommand;
use App\Plan;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = Plan::class;
    protected $objectName = 'plan';
    protected $objectTitle = 'title';
}
