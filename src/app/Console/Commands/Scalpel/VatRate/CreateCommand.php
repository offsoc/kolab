<?php

namespace App\Console\Commands\Scalpel\VatRate;

use App\Console\ObjectCreateCommand;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\VatRate::class;
    protected $objectName = 'vat-rate';
}
