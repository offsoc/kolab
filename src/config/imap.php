<?php

return [
    'uri' => env('IMAP_URI', 'ssl://kolab:11993'),
    'admin_login' => env('IMAP_ADMIN_LOGIN', 'cyrus-admin'),
    'admin_password' => env('IMAP_ADMIN_PASSWORD', null),
    'verify_peer' => env('IMAP_VERIFY_PEER', true),
    'verify_host' => env('IMAP_VERIFY_HOST', true),
    'host' => env('IMAP_HOST', '172.18.0.5'),
    'imap_port' => env('IMAP_PORT', 12143),
    'guam_port' => env('IMAP_GUAM_PORT', 9143),
];
