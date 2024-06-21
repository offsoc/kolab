<?php

namespace App\Console\Commands\Wallet;

use App\Console\ObjectListCommand;

class SettingsCommand extends ObjectListCommand
{
    protected $objectClass = \App\WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle = null;
}
