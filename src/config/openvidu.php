<?php
    return [
        'api_password' => env('OPENVIDU_API_PASSWORD', 'MY_SECRET'),
        'api_url' => env('OPENVIDU_API_URL', 'https://localhost:8443/api/'),
        'api_username' => env('OPENVIDU_API_USERNAME', 'OPENVIDUAPP'),
        'api_verify_tls' => (bool) env('OPENVIDU_API_VERIFY_TLS', true)
    ];
