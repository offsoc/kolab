<?php

namespace App\Console\Commands\Scalpel\TenantSetting;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\TenantSetting::class;
    protected $objectName = 'tenant-setting';
    protected $objectTitle = null;
}
