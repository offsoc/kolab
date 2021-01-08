<?php

namespace App\Console\Commands\Scalpel\Package;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Package::class;
    protected $objectName = 'package';
    protected $objectTitle = 'title';
}
