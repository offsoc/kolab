<?php

namespace App\Console\Commands\Package;

use App\Console\ObjectListCommand;
use App\Package;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = Package::class;
    protected $objectName = 'package';
    protected $objectTitle = 'title';
}
