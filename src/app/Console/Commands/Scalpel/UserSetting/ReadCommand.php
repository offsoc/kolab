<?php

namespace App\Console\Commands\Scalpel\UserSetting;

use App\Console\ObjectReadCommand;
use App\UserSetting;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = UserSetting::class;
    protected $objectName = 'user-setting';
    protected $objectTitle;
}
