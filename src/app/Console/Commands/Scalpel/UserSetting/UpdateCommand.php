<?php

namespace App\Console\Commands\Scalpel\UserSetting;

use App\Console\ObjectUpdateCommand;
use App\UserSetting;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = UserSetting::class;
    protected $objectName = 'user-setting';
    protected $objectTitle;
}
