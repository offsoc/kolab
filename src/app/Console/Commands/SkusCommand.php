<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class SkusCommand extends ObjectListCommand
{
    protected $objectClass = \App\Sku::class;
    protected $objectName = 'sku';
    protected $objectTitle = 'title';
}
