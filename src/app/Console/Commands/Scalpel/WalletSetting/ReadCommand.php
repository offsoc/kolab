<?php

namespace App\Console\Commands\Scalpel\WalletSetting;

use App\Console\ObjectReadCommand;
use App\WalletSetting;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle;
}
