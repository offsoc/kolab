<?php

namespace App\Console\Commands\Scalpel\Domain;

use App\Console\ObjectCreateCommand;

class CreateCommand extends ObjectCreateCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = null;
}
