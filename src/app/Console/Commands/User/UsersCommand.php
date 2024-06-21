<?php

namespace App\Console\Commands\User;

use App\Console\ObjectRelationListCommand;

class UsersCommand extends ObjectRelationListCommand
{
    protected $objectClass = \App\User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
    protected $objectRelation = 'users';
}
