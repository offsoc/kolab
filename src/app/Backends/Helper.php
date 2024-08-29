<?php

namespace App\Backends;

/**
 * Small utility functions for backends
 */
class Helper
{
    /**
     * List of default DAV folders
     */
    public static function defaultDavFolders(): array
    {
        // FIXME: I suppose this should be enabled by default, but left
        // disabled for now for compatibility with iRony-based deployments.
        // The env variable itself could be removed, as deployments can override config.
        if (!\env('DAV_WITH_DEFAULT_FOLDERS', false)) {
            return [];
        }

        return [
            [
                // FIXME: Should we use something else than 'Default'? This is
                // what Cyrus creates, and I didn't find a setting to change it,
                // we can only disable creation of the folder.
                'path' => 'Default',
                'displayname' => 'Calendar',
                'components' => ['VEVENT'],
                'type' => 'calendar',
            ],
            [
                'path' => 'Tasks',
                'displayname' => 'Tasks',
                'components' => ['VTODO'],
                'type' => 'calendar',
            ],
            [
                // FIXME: Same here, should we use 'Contacts'?
                'path' => 'Default',
                'displayname' => 'Contacts',
                'type' => 'addressbook',
            ],
        ];
    }

    /**
     * List of default IMAP folders
     */
    public static function defaultImapFolders(): array
    {
        // TODO: Move the list from config/imap.php
        return [];
    }
}
