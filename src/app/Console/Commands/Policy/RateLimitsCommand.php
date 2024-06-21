<?php

namespace App\Console\Commands\Policy;

use App\Console\ObjectListCommand;

class RateLimitsCommand extends ObjectListCommand
{
    protected $commandPrefix = 'policy';
    protected $objectClass = \App\Policy\RateLimit::class;
    protected $objectName = 'ratelimit';
    protected $objectTitle = null;
}
