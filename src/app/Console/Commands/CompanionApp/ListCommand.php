<?php

namespace App\Console\Commands\CompanionApp;

use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = \App\CompanionApp::class;
    protected $objectName = 'companion-app';
    protected $objectTitle = null;
}
