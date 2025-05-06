<?php

namespace App\Console\Commands\Scalpel\ReferralProgram;

use App\Console\ObjectUpdateCommand;
use App\ReferralProgram;

class UpdateCommand extends ObjectUpdateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = ReferralProgram::class;
    protected $objectName = 'referral-program';
    protected $objectTitle;
}
