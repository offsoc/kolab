<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class UserAliasesCommand extends ObjectListCommand
{
    protected $objectClass = \App\UserAlias::class;
    protected $objectName = 'user-alias';
    protected $objectNamePlural = 'user-aliases';
    protected $objectTitle = 'alias';
}
