<?php

namespace App\Console\Commands\Scalpel\ReferralProgram;

use App\Console\ObjectDeleteCommand;
use App\ReferralProgram;

class DeleteCommand extends ObjectDeleteCommand
{
    protected $dangerous = true;
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = ReferralProgram::class;
    protected $objectName = 'referral-program';
    protected $objectTitle;
}
