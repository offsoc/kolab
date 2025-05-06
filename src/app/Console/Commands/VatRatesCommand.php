<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;
use App\VatRate;

class VatRatesCommand extends ObjectListCommand
{
    protected $objectClass = VatRate::class;
    protected $objectName = 'vat-rate';
}
