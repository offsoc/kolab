<?php

namespace App\Console\Commands\Tenant;

use App\Console\ObjectListCommand;
use App\Tenant;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = Tenant::class;
    protected $objectName = 'tenant';
    protected $objectTitle = 'title';
}
