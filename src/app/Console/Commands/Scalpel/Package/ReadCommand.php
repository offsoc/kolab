<?php

namespace App\Console\Commands\Scalpel\Package;

use App\Console\ObjectReadCommand;
use App\Package;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Package::class;
    protected $objectName = 'package';
    protected $objectTitle = 'title';
}
