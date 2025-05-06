<?php

namespace App\Console\Commands\Scalpel\Domain;

use App\Console\ObjectCreateCommand;
use App\Domain;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle;
}
