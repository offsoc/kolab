<?php

namespace App\Console\Commands\Scalpel\Domain;

use App\Console\ObjectUpdateCommand;
use App\Domain;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';
}
