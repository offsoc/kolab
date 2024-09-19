<?php

namespace App\Console\Commands;

use App\Console\ObjectListCommand;

/**
 * List licenses.
 */
class LicensesCommand extends ObjectListCommand
{
    protected $objectClass = \App\License::class;
    protected $objectName = 'license';
}
