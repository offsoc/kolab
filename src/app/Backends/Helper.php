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
        $folders = [
            'Drafts' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.drafts',
                    '/private/specialuse' => '\Drafts',
                ],
            ],
            'Sent' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.sentitems',
                    '/private/specialuse' => '\Sent',
                ],
            ],
            'Trash' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.wastebasket',
                    '/private/specialuse' => '\Trash',
                ],
            ],
            'Spam' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.junkemail',
                    '/private/specialuse' => '\Junk',
                ],
            ],
        ];

        if (\env('IMAP_WITH_GROUPWARE_DEFAULT_FOLDERS', true)) {
            $folders = array_merge($folders, [
                'Calendar' => [
                    'metadata' => [
                        '/private/vendor/kolab/folder-type' => 'event.default',
                        '/shared/vendor/kolab/folder-type' => 'event',
                    ],
                ],
                'Contacts' => [
                    'metadata' => [
                        '/private/vendor/kolab/folder-type' => 'contact.default',
                        '/shared/vendor/kolab/folder-type' => 'event',
                    ],
                ],
                'Tasks' => [
                    'metadata' => [
                        '/private/vendor/kolab/folder-type' => 'task.default',
                        '/shared/vendor/kolab/folder-type' => 'task',
                    ],
                ],
                'Notes' => [
                    'metadata' => [
                        '/private/vendor/kolab/folder-type' => 'note.default',
                        '/shared/vendor/kolab/folder-type' => 'note',
                    ],
                ],
                'Files' => [
                    'metadata' => [
                        '/private/vendor/kolab/folder-type' => 'file.default',
                        '/shared/vendor/kolab/folder-type' => 'file',
                    ],
                ],
            ]);
        }

        return $folders;
    }
}
