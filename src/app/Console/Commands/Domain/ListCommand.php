<?php

namespace App\Console\Commands\Domain;

use App\Console\ObjectListCommand;
use App\Domain;

/**
 * List domains.
 *
 * Example usage:
 *
 * ```
 * $ ./artisan domains
 * 96217419
 * 502526624
 * 539082236
 * (...)
 * ```
 *
 * To include specific attributes, use `--attr` (allowed multiple times):
 *
 * ```
 * $ ./artisan domains --attr=namespace --attr=status
 * 96217419 attorneymail.ch 51
 * 502526624 example.net 51
 * 539082236 collaborative.li 51
 * (...)
 * ```
 */
class ListCommand extends ObjectListCommand
{
    protected $objectClass = Domain::class;
    protected $objectName = 'domain';
    protected $objectTitle = 'namespace';
}
