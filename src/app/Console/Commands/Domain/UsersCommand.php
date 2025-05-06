<?php

namespace App\Console\Commands\Domain;

use App\Console\ObjectRelationListCommand;
use App\Domain;

class UsersCommand extends ObjectRelationListCommand
{
    protected $objectClass = Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';
    protected $objectRelation = 'users';
}
