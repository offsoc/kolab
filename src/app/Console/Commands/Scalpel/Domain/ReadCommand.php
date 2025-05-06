<?php

namespace App\Console\Commands\Scalpel\Domain;

use App\Console\ObjectReadCommand;
use App\Domain;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';
}
