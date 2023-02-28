<?php

namespace App\Console\Commands\Scalpel\VatRate;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\VatRate::class;
    protected $objectName = 'vat-rate';
}
