<?php

namespace App\Console\Commands\Scalpel\User;

use App\Console\ObjectReadCommand;
use App\User;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
}
