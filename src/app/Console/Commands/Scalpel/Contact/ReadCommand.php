<?php

namespace App\Console\Commands\Scalpel\Contact;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Contact::class;
    protected $objectName = 'contact';
    protected $objectTitle = null;
}
