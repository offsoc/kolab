<?php

namespace App\Console\Commands\Scalpel\Discount;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle = null;
}
