<?php

return [
    'api_token' => env('MEET_SERVER_TOKEN', 'MY_SECRET'),
    'api_urls' => explode(
        ',',
        env('MEET_SERVER_URLS', "https://localhost:12443/meetmedia/api/,https://localhost:12444/meetmedia/api/")
    ),
    'api_verify_tls' => (bool) env('MEET_SERVER_VERIFY_TLS', true),
    'webhook_token' => env('MEET_WEBHOOK_TOKEN', 'MY_SECRET'),
];
