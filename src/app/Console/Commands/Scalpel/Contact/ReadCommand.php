<?php

namespace App\Console\Commands\Scalpel\Contact;

use App\Console\ObjectReadCommand;
use App\Contact;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Contact::class;
    protected $objectName = 'contact';
    protected $objectTitle;
}
