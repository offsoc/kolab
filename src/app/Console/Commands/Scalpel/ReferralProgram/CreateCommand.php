<?php

namespace App\Console\Commands\Scalpel\ReferralProgram;

use App\Console\ObjectCreateCommand;

class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\ReferralProgram::class;
    protected $objectName = 'referral-program';
    protected $objectTitle = null;
}
