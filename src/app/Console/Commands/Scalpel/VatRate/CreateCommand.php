<?php

namespace App\Console\Commands\Scalpel\VatRate;

use App\Console\ObjectCreateCommand;
use App\VatRate;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = VatRate::class;
    protected $objectName = 'vat-rate';
}
