<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class UsersCommand extends ObjectListCommand
{
    protected $objectClass = \App\User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
}
