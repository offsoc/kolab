<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class WalletsCommand extends ObjectListCommand
{
    protected $objectClass = \App\Wallet::class;
    protected $objectName = 'wallet';
    protected $objectTitle = null;
}
