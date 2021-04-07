<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class UserSettingsCommand extends ObjectListCommand
{
    protected $objectClass = \App\UserSetting::class;
    protected $objectName = 'user-setting';
    protected $objectTitle = null;
}
