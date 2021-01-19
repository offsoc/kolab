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
    protected $commandPrefix = null;

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


    /**
     * List of cache keys to refresh after updating/creating an object
     *
     * @var array
     */
    protected $cacheKeys = [];

    /**
     * Reset the cache for specified object using defined cacheKeys.
     *
     * @param object $object The object that was updated/created
     */
    protected function cacheRefresh($object): void
    {
        foreach ($this->cacheKeys as $cacheKey) {
            foreach ($object->toArray() as $propKey => $propValue) {
                if (!is_object($propValue)) {
                    $cacheKey = str_replace('%' . $propKey . '%', $propValue, $cacheKey);
                }
            }

            Cache::forget($cacheKey);
        }
    }
}
