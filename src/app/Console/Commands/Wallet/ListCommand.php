<?php

namespace App\Console\Commands\Wallet;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\Wallet::class;
    protected $objectName = 'wallet';
    protected $objectTitle = null;
}
