<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

class TenantsCommand extends ObjectListCommand
{
    protected $objectClass = \App\Tenant::class;
    protected $objectName = 'tenant';
    protected $objectTitle = 'title';
}
