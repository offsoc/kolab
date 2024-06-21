<?php

namespace App\Console\Commands\Domain;

use App\Console\ObjectRelationListCommand;

class UsersCommand extends ObjectRelationListCommand
{
    protected $objectClass = \App\Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';
    protected $objectRelation = 'users';
}
