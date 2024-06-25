<?php

namespace App\Console\Commands\Package;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\Package::class;
    protected $objectName = 'package';
    protected $objectTitle = 'title';
}
