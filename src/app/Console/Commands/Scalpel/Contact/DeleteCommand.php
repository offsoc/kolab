<?php

namespace App\Console\Commands\Scalpel\Contact;

use App\Console\ObjectDeleteCommand;
use App\Contact;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = true;
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = Contact::class;
    protected $objectName = 'contact';
    protected $objectTitle;
}
