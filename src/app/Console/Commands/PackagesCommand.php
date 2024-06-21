<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class PackagesCommand extends ObjectListCommand
{
    protected $objectClass = \App\Package::class;
    protected $objectName = 'package';
    protected $objectTitle = 'title';
}
