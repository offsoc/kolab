<?php

return [
    'uri' => env('IMAP_URI', '127.0.0.1'),
    'admin_login' => env('IMAP_ADMIN_LOGIN', 'cyrus-admin'),
    'admin_password' => env('IMAP_ADMIN_PASSWORD', null),
    'verify_peer' => env('IMAP_VERIFY_PEER', true),
    'verify_host' => env('IMAP_VERIFY_HOST', true),
    'host' => env('IMAP_HOST', '127.0.0.1'),
    'guam_tls_port' => env('IMAP_GUAM_TLS_PORT', 9993),
    'guam_port' => env('IMAP_GUAM_PORT', 9143),
    'tls_port' => env('IMAP_TLS_PORT', 11993),
    'port' => env('IMAP_PORT', 12143),
];
