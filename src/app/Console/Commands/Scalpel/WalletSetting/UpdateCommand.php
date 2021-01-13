<?php

namespace App\Console\Commands\Scalpel\WalletSetting;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $cacheKeys = ['wallet_settings_%wallet_id%'];
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\WalletSetting::class;
    protected $objectName = 'wallet-setting';
    protected $objectTitle = null;
}
