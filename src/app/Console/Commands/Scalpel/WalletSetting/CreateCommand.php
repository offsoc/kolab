<?php

namespace App\Console\Commands\Scalpel\WalletSetting;

use App\Console\ObjectCreateCommand;
use App\WalletSetting;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle;
}
