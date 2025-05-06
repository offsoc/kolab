<?php

namespace App\Console\Commands\User;

use App\Console\ObjectDeleteCommand;
use App\User;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = false;
    protected $hidden = false;

    protected $objectClass = User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
}
