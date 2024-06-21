<?php

namespace App\Console\Commands\Scalpel\Wallet;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Wallet::class;
    protected $objectName = 'wallet';
    protected $objectTitle = null;
}
