<?php

namespace App\Console\Commands\Scalpel\ReferralProgram;

use App\Console\ObjectReadCommand;

class ReadCommand extends ObjectReadCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\ReferralProgram::class;
    protected $objectName = 'referral-program';
    protected $objectTitle = null;
}
