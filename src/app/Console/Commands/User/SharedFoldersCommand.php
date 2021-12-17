<?php

namespace App\Console\Commands\User;

use App\Console\ObjectRelationListCommand;

class SharedFoldersCommand extends ObjectRelationListCommand
{
    protected $objectClass = \App\User::class;
    protected $objectName = 'user';
    protected $objectTitle = 'email';
    protected $objectRelation = 'sharedFolders';
}
