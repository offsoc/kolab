<?php

namespace App\Console\Commands\Policy;

use App\Console\ObjectListCommand;
use App\Policy\RateLimit;

class RateLimitsCommand extends ObjectListCommand
{
    protected $commandPrefix = 'policy';
    protected $objectClass = RateLimit::class;
    protected $objectName = 'ratelimit';
    protected $objectTitle;
}
