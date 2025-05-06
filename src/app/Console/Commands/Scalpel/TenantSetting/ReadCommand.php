<?php

namespace App\Console\Commands\Scalpel\TenantSetting;

use App\Console\ObjectReadCommand;
use App\TenantSetting;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = TenantSetting::class;
    protected $objectName = 'tenant-setting';
    protected $objectTitle;
}
