<?php

namespace App\Console\Commands\Scalpel\Transaction;

use App\Console\ObjectReadCommand;
use App\Transaction;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Transaction::class;
    protected $objectName = 'transaction';
    protected $objectTitle;
}
