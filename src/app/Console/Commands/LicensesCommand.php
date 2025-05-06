<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;
use App\License;

/**
 * List licenses.
 */
class LicensesCommand extends ObjectListCommand
{
    protected $objectClass = License::class;
    protected $objectName = 'license';
}
