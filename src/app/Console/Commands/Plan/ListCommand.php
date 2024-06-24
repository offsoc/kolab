<?php

namespace App\Console\Commands\Plan;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\Plan::class;
    protected $objectName = 'plan';
    protected $objectTitle = 'title';
}
