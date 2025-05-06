<?php

namespace App\Console\Commands\AuthAttempt;

use App\AuthAttempt;
use App\Console\ObjectDeleteCommand;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = false;
    protected $hidden = false;

    protected $objectClass = AuthAttempt::class;
    protected $objectName = 'authattempt';
    protected $objectTitle = 'id';
}
