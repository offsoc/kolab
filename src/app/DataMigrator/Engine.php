<?php

namespace App\DataMigrator;

/**
 * Data migration engine
 */
class Engine
{
    /**
     * Execute migration for the specified user
     */
    public static function migrate(Account $source, Account $destination, array $options = [])
    {
        // For now we support only EWS, but we can have
        // IMAP migrator or other, so this will be a factory
        // for selecting (automatically?) the migration engine

        $driver = new EWS;

        $driver->migrate($source, $destination, $options);
    }
}
