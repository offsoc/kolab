<?php

namespace App\Console\Commands\User;

use App\Console\ObjectRelationListCommand;
use App\User;

class ResourcesCommand extends ObjectRelationListCommand
{
    protected $objectClass = User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
    protected $objectRelation = 'resources';
}
