<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;
use App\UserAlias;

class UserAliasesCommand extends ObjectListCommand
{
    protected $objectClass = UserAlias::class;
    protected $objectName = 'user-alias';
    protected $objectNamePlural = 'user-aliases';
    protected $objectTitle = 'alias';
}
