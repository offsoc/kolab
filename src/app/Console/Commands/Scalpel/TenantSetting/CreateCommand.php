<?php

namespace App\Console\Commands\Scalpel\TenantSetting;

use App\Console\ObjectCreateCommand;
use App\TenantSetting;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = TenantSetting::class;
    protected $objectName = 'tenant-setting';
    protected $objectTitle;
}
