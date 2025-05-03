<?php

namespace App\Console\Commands\Scalpel\Contact;

use App\Console\ObjectDeleteCommand;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = true;
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\Contact::class;
    protected $objectName = 'contact';
    protected $objectTitle = null;
}
