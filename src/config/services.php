<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, SparkPost and others. This file provides a sane default
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'payment_provider' => env('PAYMENT_PROVIDER', 'mollie'),

    'mollie' => [
        'key' => env('MOLLIE_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'coinbase' => [
        'key' => env('COINBASE_KEY'),
        'webhook_secret' => env('COINBASE_WEBHOOK_SECRET'),
        'api_verify_tls' => env('COINBASE_VERIFY_TLS', true),
    ],

    'openexchangerates' => [
        'api_key' => env('OPENEXCHANGERATES_API_KEY', null),
    ],

    'dav' => [
        'uri' => env('DAV_URI', 'https://proxy/'),
        'verify' => (bool) env('DAV_VERIFY', true),
    ],

    'autodiscover' => [
        'uri' => env('AUTODISCOVER_URI', env('APP_URL', 'http://localhost')),
    ],

    'activesync' => [
        'uri' => env('ACTIVESYNC_URI', 'https://proxy/Microsoft-Server-ActiveSync'),
    ],

    'wopi' => [
        'uri' => env('WOPI_URI', 'http://roundcube/chwala/'),
    ],

    'webmail' => [
        'uri' => env('WEBMAIL_URI', 'http://roundcube/roundcubemail/'),
    ]
];
