<?php

namespace App\Console\Commands\Scalpel\Sku;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Sku::class;
    protected $objectName = 'sku';
    protected $objectTitle = null; // SKU title is not unique
}
