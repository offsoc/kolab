<?php

namespace App\Console\Commands\Scalpel\UserSetting;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\UserSetting::class;
    protected $objectName = 'user-setting';
    protected $objectTitle = null;
}
