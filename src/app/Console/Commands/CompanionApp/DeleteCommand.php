<?php

namespace App\Console\Commands\CompanionApp;

use App\Console\ObjectDeleteCommand;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = false;
    protected $hidden = false;

    protected $objectClass = \App\CompanionApp::class;
    protected $objectName = 'companion-app';
    protected $objectTitle = null;
}
