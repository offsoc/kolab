<?php

namespace App\Console\Commands\User;

use App\Console\ObjectRelationListCommand;
use App\User;

class WalletsCommand extends ObjectRelationListCommand
{
    protected $objectClass = User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
    protected $objectRelation = 'wallets';
}
