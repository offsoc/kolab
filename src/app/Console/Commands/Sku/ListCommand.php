<?php

namespace App\Console\Commands\Sku;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\Sku::class;
    protected $objectName = 'sku';
    protected $objectTitle = 'title';
}
