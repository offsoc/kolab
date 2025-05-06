<?php

namespace App\Console\Commands\Scalpel\UserSetting;

use App\Console\ObjectCreateCommand;
use App\UserSetting;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = UserSetting::class;
    protected $objectName = 'user-setting';
    protected $objectTitle;
}
