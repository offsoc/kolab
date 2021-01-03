<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class WalletSettingsCommand extends ObjectListCommand
{
    protected $objectClass = \App\WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle = null;
}
