<?php

namespace App\Console\Commands\Scalpel\User;

use App\Console\ObjectUpdateCommand;
use App\User;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
}
