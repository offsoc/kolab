<?php

namespace App\Console\Commands\Scalpel\Wallet;

use App\Console\ObjectReadCommand;
use App\Wallet;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Wallet::class;
    protected $objectName = 'wallet';
    protected $objectTitle;
}
