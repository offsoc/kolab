<?php

return [

    // Enables PGP keypair generation on user creation
    'enable' => env('PGP_ENABLE', false),

    // gpg binary location
    'binary' => env('PGP_BINARY'),

    // gpg-agent location
    'agent' => env('PGP_AGENT'),

    // gpgconf location
    'gpgconf' => env('PGP_GPGCONF'),

    // Default size of the new RSA key
    'length' => (int) env('PGP_LENGTH', 3072),

];
