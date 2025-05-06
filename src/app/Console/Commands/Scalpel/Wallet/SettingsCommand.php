<?php

namespace App\Console\Commands\Scalpel\Wallet;

use App\Console\ObjectRelationListCommand;
use App\Wallet;

class SettingsCommand extends ObjectRelationListCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Wallet::class;
    protected $objectName = 'wallet';
    protected $objectTitle;
    protected $objectRelation = 'settings';
}
