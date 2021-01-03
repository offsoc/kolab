<?php

namespace App\Console\Commands\Scalpel\WalletSetting;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle = null;
}
