<?php

namespace App\Console\Commands\Scalpel\Transaction;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Transaction::class;
    protected $objectName = 'transaction';
    protected $objectTitle = null;
}
