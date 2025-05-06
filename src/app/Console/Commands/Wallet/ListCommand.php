<?php

namespace App\Console\Commands\Wallet;

use App\Console\ObjectListCommand;
use App\Wallet;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = Wallet::class;
    protected $objectName = 'wallet';
    protected $objectTitle;
}
