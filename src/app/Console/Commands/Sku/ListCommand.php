<?php

namespace App\Console\Commands\Sku;

use App\Console\ObjectListCommand;
use App\Sku;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = Sku::class;
    protected $objectName = 'sku';
    protected $objectTitle = 'title';
}
