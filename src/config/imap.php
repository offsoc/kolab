<?php

return [
    'uri' => env('IMAP_URI', '127.0.0.1'),
    'admin_login' => env('IMAP_ADMIN_LOGIN', 'cyrus-admin'),
    'admin_password' => env('IMAP_ADMIN_PASSWORD', null),
    'verify_peer' => env('IMAP_VERIFY_PEER', true),
    'verify_name' => env('IMAP_VERIFY_NAME', true),
    'cafile' => env('IMAP_CAFILE', null),
];
