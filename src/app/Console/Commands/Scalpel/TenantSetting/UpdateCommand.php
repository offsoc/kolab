<?php

namespace App\Console\Commands\Scalpel\TenantSetting;

use App\Console\ObjectUpdateCommand;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $cacheKeys = ['app\tenant_settings_%tenant_id%'];
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\TenantSetting::class;
    protected $objectName = 'tenant-setting';
    protected $objectTitle = null;
}
