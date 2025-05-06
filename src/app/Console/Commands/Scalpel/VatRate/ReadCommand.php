<?php

namespace App\Console\Commands\Scalpel\VatRate;

use App\Console\ObjectReadCommand;
use App\VatRate;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = VatRate::class;
    protected $objectName = 'vat-rate';
}
