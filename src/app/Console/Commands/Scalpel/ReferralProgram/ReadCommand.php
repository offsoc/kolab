<?php

namespace App\Console\Commands\Scalpel\ReferralProgram;

use App\Console\ObjectReadCommand;
use App\ReferralProgram;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = ReferralProgram::class;
    protected $objectName = 'referral-program';
    protected $objectTitle;
}
