<?php

namespace App\Console\Commands\Scalpel\ReferralProgram;

use App\Console\ObjectCreateCommand;
use App\ReferralProgram;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = ReferralProgram::class;
    protected $objectName = 'referral-program';
    protected $objectTitle;
}
