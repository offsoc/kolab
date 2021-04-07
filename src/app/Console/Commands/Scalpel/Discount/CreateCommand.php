<?php

namespace App\Console\Commands\Scalpel\Discount;

use App\Console\ObjectCreateCommand;

class CreateCommand extends ObjectCreateCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle = null;
}
