<?php

namespace App\Console\Commands\Scalpel\VatRate;

use App\Console\ObjectUpdateCommand;
use App\VatRate;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = VatRate::class;
    protected $objectName = 'vat-rate';
}
