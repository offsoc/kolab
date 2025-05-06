<?php

namespace App\Console\Commands\Scalpel\Contact;

use App\Console\ObjectCreateCommand;
use App\Contact;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Contact::class;
    protected $objectName = 'contact';
    protected $objectTitle;
}
