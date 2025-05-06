<?php

namespace App\Console\Commands\Scalpel\Discount;

use App\Console\ObjectCreateCommand;
use App\Discount;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle;
}
