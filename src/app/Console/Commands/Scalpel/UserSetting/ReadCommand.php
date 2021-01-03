<?php

namespace App\Console\Commands\Scalpel\UserSetting;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\UserSetting::class;
    protected $objectName = 'user-setting';
    protected $objectTitle = null;
}
