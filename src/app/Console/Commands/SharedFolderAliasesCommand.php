<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;
use App\SharedFolderAlias;

class SharedFolderAliasesCommand extends ObjectListCommand
{
    protected $objectClass = SharedFolderAlias::class;
    protected $objectName = 'shared-folder-alias';
    protected $objectNamePlural = 'shared-folder-aliases';
    protected $objectTitle = 'alias';
}
