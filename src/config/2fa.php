<?php

return [
    'totp' => [
        'digits' => (int) env('MFA_TOTP_DIGITS', 6),
        'interval' => (int) env('MFA_TOTP_INTERVAL', 30),
        'digest' => env('MFA_TOTP_DIGEST', 'sha1'),
        'issuer' => env('APP_NAME', 'Laravel'),
    ],
];
