<?php

namespace App\Console\Commands\Scalpel\Domain;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';
}
