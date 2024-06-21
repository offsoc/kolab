<?php

namespace App\Console\Commands\Scalpel\Sku;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Sku::class;
    protected $objectName = 'sku';
    protected $objectTitle = 'title';
}
