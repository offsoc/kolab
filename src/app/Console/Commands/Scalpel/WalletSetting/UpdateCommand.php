<?php

namespace App\Console\Commands\Scalpel\WalletSetting;

use App\Console\ObjectUpdateCommand;
use App\WalletSetting;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle;
}
