<?php

namespace App\Console;

use Illuminate\Support\Facades\Cache;

abstract class ObjectCommand extends Command
{
    /**
     * Specify a command prefix, if any.
     *
     * For example, \App\Console\Commands\Scalpel\User\CreateCommand uses prefix 'scalpel'.
     *
     * @var string
     */
    protected $commandPrefix = '';

    /**
     * The object class that we are operating on, for example \App\User::class
     *
     * @var string
     */
    protected $objectClass;

    /**
     * The (simple) object name, such as 'domain' or 'user'. Corresponds with the mandatory command-line option
     * to identify the object from its corresponding model.
     *
     * @var string
     */
    protected $objectName;

    /**
     * The plural of the object name, if something specific (goose -> geese).
     *
     * @var string
     */
    protected $objectNamePlural;

    /**
     * A column name other than the primary key can be used to identify an object, such as 'email' for users,
     * 'namespace' for domains, and 'title' for SKUs.
     *
     * @var string
     */
    protected $objectTitle;

    /**
     * Placeholder for column name attributes for objects, from which command-line switches and attribute names can be
     * generated.
     *
     * @var array
     */
    protected $properties;
}
