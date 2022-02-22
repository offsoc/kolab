<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class SharedFolderAliasesCommand extends ObjectListCommand
{
    protected $objectClass = \App\SharedFolderAlias::class;
    protected $objectName = 'shared-folder-alias';
    protected $objectNamePlural = 'shared-folder-aliases';
    protected $objectTitle = 'alias';
}
