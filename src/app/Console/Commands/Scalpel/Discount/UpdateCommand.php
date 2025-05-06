<?php

namespace App\Console\Commands\Scalpel\Discount;

use App\Console\ObjectUpdateCommand;
use App\Discount;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle;
}
