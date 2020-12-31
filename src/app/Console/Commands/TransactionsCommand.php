<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class TransactionsCommand extends ObjectListCommand
{
    protected $objectClass = \App\Transaction::class;
    protected $objectName = 'transaction';
    protected $objectTitle = null;
}
