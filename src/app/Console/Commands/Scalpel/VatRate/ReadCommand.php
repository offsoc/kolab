<?php

namespace App\Console\Commands\Scalpel\VatRate;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\VatRate::class;
    protected $objectName = 'vat-rate';
}
