<?php

namespace App\Console\Commands\Scalpel\Contact;

use App\Console\ObjectUpdateCommand;
use App\Contact;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Contact::class;
    protected $objectName = 'contact';
    protected $objectTitle;
}
