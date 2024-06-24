<?php

namespace App\Console\Commands\Tenant;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\Tenant::class;
    protected $objectName = 'tenant';
    protected $objectTitle = 'title';
}
