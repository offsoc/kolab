<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class VatRatesCommand extends ObjectListCommand
{
    protected $objectClass = \App\VatRate::class;
    protected $objectName = 'vat-rate';
}
