<?php
    return [
        /* api_key available in: Firebase Console -> Project Settings -> CLOUD MESSAGING -> Server key*/
        'api_key' => env('FIREBASE_API_KEY'),
        'api_url' => env('FIREBASE_API_URL', 'https://fcm.googleapis.com/fcm/send'),
        'api_verify_tls' => (bool) env('FIREBASE_API_VERIFY_TLS', true)
    ];
