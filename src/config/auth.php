<?php

use App\User;

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option controls the default authentication "guard" and password
    | reset options for your application. You may change these defaults
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => 'api',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | here which uses session storage and the Eloquent user provider.
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'passport',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication drivers have a user provider. This defines how the
    | users are actually retrieved out of your database or other storage
    | mechanisms used by this application to persist your user's data.
    |
    | If you have multiple user tables or models you may configure multiple
    | sources which represent each model / table. These sources may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | You may specify multiple password reset configurations if you have more
    | than one user table or model in the application and you want to have
    | separate password reset settings based on the specific user types.
    |
    | The expire time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | times out and the user is prompted to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    */

    'password_timeout' => 10800,

    /*
    |--------------------------------------------------------------------------
    | OAuth Proxy Authentication
    |--------------------------------------------------------------------------
    |
    | If you are planning to use your application to self-authenticate as a
    | proxy, you can define the client and grant type to use here. This is
    | sometimes the case when a trusted Single Page Application doesn't
    | use a backend to send the authentication request, but instead
    | relies on the API to handle proxying the request to itself.
    |
     */

    'proxy' => [
        'client_id' => env('PASSPORT_PROXY_OAUTH_CLIENT_ID'),
        'client_secret' => env('PASSPORT_PROXY_OAUTH_CLIENT_SECRET'),
    ],

    'synapse' => [
        'client_id' => env('PASSPORT_SYNAPSE_OAUTH_CLIENT_ID'),
        'client_secret' => env('PASSPORT_SYNAPSE_OAUTH_CLIENT_SECRET'),
    ],

    'sso' => [
        'client_id' => env('PASSPORT_WEBMAIL_SSO_CLIENT_ID'),
        'client_secret' => env('PASSPORT_WEBMAIL_SSO_CLIENT_SECRET'),
    ],

    'token_expiry_minutes' => (int) env('OAUTH_TOKEN_EXPIRY', 60),
    'refresh_token_expiry_minutes' => (int) env('OAUTH_REFRESH_TOKEN_EXPIRY', 30 * 24 * 60),
];
