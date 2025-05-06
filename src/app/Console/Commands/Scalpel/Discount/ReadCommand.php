<?php

namespace App\Console\Commands\Scalpel\Discount;

use App\Console\ObjectReadCommand;
use App\Discount;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle;
}
