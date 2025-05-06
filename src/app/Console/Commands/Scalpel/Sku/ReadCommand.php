<?php

namespace App\Console\Commands\Scalpel\Sku;

use App\Console\ObjectReadCommand;
use App\Sku;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Sku::class;
    protected $objectName = 'sku';
    protected $objectTitle = 'title';
}
