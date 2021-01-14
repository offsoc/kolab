<?php

namespace App\Console\Commands\Scalpel\Discount;

use App\Console\ObjectDeleteCommand;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = true;
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle = null;
}
