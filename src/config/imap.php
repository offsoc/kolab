<?php

if (!function_exists("imap_defaultFolders")) {
    function imap_defaultFolders() {
        $folders = [
            'Drafts' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.drafts',
                ],
            ],
            'Sent' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.sentitems',
                ],
            ],
            'Trash' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.wastebasket',
                ],
            ],
            'Spam' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.junkemail',
                ],
            ],
        ];
        if (env('IMAP_WITH_GROUPWARE_DEFAULT_FOLDERS', true)) {
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

return [
    'uri' => env('IMAP_URI', 'ssl://kolab:11993'),
    'admin_login' => env('IMAP_ADMIN_LOGIN', 'cyrus-admin'),
    'admin_password' => env('IMAP_ADMIN_PASSWORD', null),
    'verify_peer' => env('IMAP_VERIFY_PEER', true),
    'verify_host' => env('IMAP_VERIFY_HOST', true),
    'host' => env('IMAP_HOST', '172.18.0.5'),
    'imap_port' => env('IMAP_PORT', 12143),
    'guam_port' => env('IMAP_GUAM_PORT', 9143),
    'default_folders' => imap_defaultFolders()
];
