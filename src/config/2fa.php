<?php

return [

    'totp' => [
        'digits' => (int) env('2FA_TOTP_DIGITS', 6),
        'interval' => (int) env('2FA_TOTP_INTERVAL', 30),
        'digest' => env('2FA_TOTP_DIGEST', 'sha1'),
        'issuer' => env('APP_NAME', 'Laravel'),
    ],

    'dsn' => env('2FA_DSN'),

];
