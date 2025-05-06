<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;
use App\UserSetting;

class UserSettingsCommand extends ObjectListCommand
{
    protected $objectClass = UserSetting::class;
    protected $objectName = 'user-setting';
    protected $objectTitle;
}
