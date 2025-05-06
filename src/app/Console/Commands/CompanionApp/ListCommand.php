<?php

namespace App\Console\Commands\CompanionApp;

use App\CompanionApp;
use App\Console\ObjectListCommand;

class ListCommand extends ObjectListCommand
{
    protected $objectClass = CompanionApp::class;
    protected $objectName = 'companion-app';
    protected $objectTitle;
}
