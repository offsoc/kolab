<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;
use App\Transaction;

class TransactionsCommand extends ObjectListCommand
{
    protected $objectClass = Transaction::class;
    protected $objectName = 'transaction';
    protected $objectTitle;
}
