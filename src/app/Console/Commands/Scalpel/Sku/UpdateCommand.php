<?php

namespace App\Console\Commands\Scalpel\Sku;

use App\Console\ObjectUpdateCommand;
use App\Sku;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Sku::class;
    protected $objectName = 'sku';
    protected $objectTitle; // SKU title is not unique
}
