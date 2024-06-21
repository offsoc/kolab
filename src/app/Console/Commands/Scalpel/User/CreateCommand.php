<?php

namespace App\Console\Commands\Scalpel\User;

use App\Console\ObjectCreateCommand;

/**
 * Create a user at the lowest level of control over the exact database entry.
 *
 * For example:
 *
 * ```
 * $ ./artisan scalpel:user:create --id=3 --email=john.doe@kolab.local --password=somehash --status=3
 * ```
 *
 * **NOTE**: Executes the model's create() function, and therefore the necessary observer routines (meaning, basically,
 * actions such as the creation of wallet will be executed), **HOWEVER** no entitlements will be associated with the
 * user.
 */
class CreateCommand extends ObjectCreateCommand
{
    protected $hidden = true;

    protected $commandPrefix = 'scalpel';
    protected $objectClass = \App\User::class;
    protected $objectName = 'user';
    protected $objectTitle = null;
}
