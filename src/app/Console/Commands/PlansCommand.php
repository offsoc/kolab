<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class PlansCommand extends ObjectListCommand
{
    protected $objectClass = \App\Plan::class;
    protected $objectName = 'plan';
    protected $objectTitle = 'title';
}
