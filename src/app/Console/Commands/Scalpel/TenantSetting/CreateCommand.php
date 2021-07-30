<?php

namespace App\Console\Commands\Scalpel\TenantSetting;

use App\Console\ObjectCreateCommand;

class CreateCommand extends ObjectCreateCommand
{
    protected $cacheKeys = ['app\tenant_settings_%tenant_id%'];
    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\TenantSetting::class;
    protected $objectName = 'tenant-setting';
    protected $objectTitle = null;
}
