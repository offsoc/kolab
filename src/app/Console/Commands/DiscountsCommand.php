<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class DiscountsCommand extends ObjectListCommand
{
    protected $objectClass = \App\Discount::class;
    protected $objectName = 'discount';
    protected $objectTitle = null;
}
