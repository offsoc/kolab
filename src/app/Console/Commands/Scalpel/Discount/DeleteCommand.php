<?php

namespace App\Console\Commands\Scalpel\Discount;

use App\Console\ObjectDeleteCommand;
use App\Discount;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = true;
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle;
}
