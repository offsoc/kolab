<?php

namespace App;

use App\DataMigrator\Account;

/**
 * Data migration factory
 */
class DataMigrator
{
    /**
     * Execute migration for the specified user
     */
    public static function migrate(Account $source, Account $destination)
    {
        // For now we support only EWS, but we can have
        // IMAP migrator or other, so this will be a factory
        // for selecting (automatically?) the migration engine

        $driver = new DataMigrator\EWS;

        $driver->migrate($source, $destination);
    }
}
