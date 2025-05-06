<?php

namespace App\Console\Commands\Wallet;

use App\Console\ObjectListCommand;
use App\WalletSetting;

class SettingsCommand extends ObjectListCommand
{
    protected $objectClass = WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle;
}
