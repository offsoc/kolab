<?php

namespace App\Console\Commands\Scalpel\TenantSetting;

use App\Console\ObjectUpdateCommand;
use App\TenantSetting;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = TenantSetting::class;
    protected $objectName = 'tenant-setting';
    protected $objectTitle;
}
