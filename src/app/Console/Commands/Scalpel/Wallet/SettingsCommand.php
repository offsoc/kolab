<?php

namespace App\Console\Commands\Scalpel\Wallet;

use App\Console\ObjectRelationListCommand;

class SettingsCommand extends ObjectRelationListCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Wallet::class;
    protected $objectName = 'wallet';
    protected $objectTitle = null;
    protected $objectRelation = 'settings';
}
